<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Connector;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use Rossel\RosselKafka\Exception\MessageNotDeliveredException;
use Rossel\RosselKafka\Exception\UnauthorizedTopicOperationException;
use Rossel\RosselKafka\Model\MessageInterface;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\Ssl\SslCertificateProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class KafkaConnector implements KafkaConnectorInterface
{
    /** @var array<string, string>|null */
    private ?array $commonGlobalConfig = null;

    private ?Producer $rdKafkaProducer = null;

    // Set by the delivery report of the message being sent.
    private ?string $deliveryError = null;

    /** @var array<string, ProducerTopic> */
    private array $producerTopics = [];

    public function __construct(
        #[Autowire(param: 'rossel_kafka.broker.url')]
        private readonly string $brokerUrl,
        #[Autowire(param: 'rossel_kafka.producer.app_name')]
        private readonly string $appName,
        #[Autowire(param: 'rossel_kafka.broker.authentication.sasl_username')]
        private readonly ?string $saslUsername,
        #[Autowire(param: 'rossel_kafka.broker.authentication.sasl_password')]
        private readonly ?string $saslPassword,
        #[Autowire(param: 'rossel_kafka.broker.authentication.sasl_mechanism')]
        private readonly ?string $saslMechanism,
        #[Autowire(param: 'rossel_kafka.broker.authentication.ssl_ca_certificate_url')]
        private readonly ?string $sslCaCertificateUrl,
        #[Autowire(param: 'rossel_kafka.broker.authentication.ssl_ca_certificate_path')]
        private readonly ?string $sslCaCertificatePath,
        #[Autowire(param: 'rossel_kafka.broker.authentication.ssl_client_certificate')]
        private readonly ?string $sslClientCertificate,
        #[Autowire(param: 'rossel_kafka.broker.authentication.ssl_client_key')]
        private readonly ?string $sslClientKey,
        #[Autowire(param: 'rossel_kafka.broker.authentication.ssl_client_key_password')]
        private readonly ?string $sslClientKeyPassword,
        private readonly SslCertificateProvider $sslCertificateProvider,
        #[Autowire(param: 'rossel_kafka.broker.debug')]
        private readonly ?string $debugLevel = null,
        private readonly int $deliveryTimeoutMs = 30_000,
    ) {
    }

    /**
     * Send a message to a Kafka topic and wait until the broker acknowledges it, so that a caller can acknowledge
     * its own source once this method returns. The record value is the raw body, metadata travels in Kafka headers.
     *
     * @throws MessageNotDeliveredException when the broker refuses the message or does not acknowledge it in time
     */
    public function send(Topic $topic, MessageInterface $message): void
    {
        if (!$topic->isProducible()) {
            throw UnauthorizedTopicOperationException::produce($topic);
        }

        $producer = $this->producer();
        $this->producerTopics[$topic->getName()] ??= $producer->newTopic($topic->getName());
        $this->producerTopics[$topic->getName()]->producev(
            \RD_KAFKA_PARTITION_UA,
            0,
            $message->getBody(),
            $message->getKey(),
            $message->getHeaders(),
        );
        // librdkafka checks for expired messages once per second: wait past that for its delivery report.
        $flushResult = $producer->flush($this->deliveryTimeoutMs + 1_000);
        $deliveryError = $this->deliveryError;
        $this->deliveryError = null;

        if (\RD_KAFKA_RESP_ERR_NO_ERROR !== $flushResult) {
            throw MessageNotDeliveredException::to($topic, rd_kafka_err2str($flushResult));
        }

        if (null !== $deliveryError) {
            throw MessageNotDeliveredException::to($topic, $deliveryError);
        }
    }

    /**
     * Create a consumer subscribed to the topic.
     */
    public function createConsumer(Topic $topic): KafkaConsumer
    {
        if (!$topic->isConsumable()) {
            throw UnauthorizedTopicOperationException::consume($topic);
        }

        $consumer = new KafkaConsumer($this->conf($this->consumerConfig()));
        $consumer->subscribe([$topic->getName()]);

        return $consumer;
    }

    // Created on first send, so consumer-only apps never open a producer.
    private function producer(): Producer
    {
        if (null === $this->rdKafkaProducer) {
            $conf = $this->conf($this->producerConfig());
            // Served by flush(): the broker's answer for each message.
            $conf->setDrMsgCb(function (Producer $producer, Message $message): void {
                if (\RD_KAFKA_RESP_ERR_NO_ERROR !== $message->err) {
                    $this->deliveryError = rd_kafka_err2str($message->err);
                }
            });
            $this->rdKafkaProducer = new Producer($conf);
        }

        return $this->rdKafkaProducer;
    }

    /**
     * @param array<string, string> $config
     */
    private function conf(array $config): Conf
    {
        $conf = new Conf();

        foreach ($config as $name => $value) {
            $conf->set($name, $value);
        }

        return $conf;
    }

    /**
     * @return array<string, string>
     */
    private function commonGlobalConfig(): array
    {
        return $this->commonGlobalConfig ??= $this->buildCommonGlobalConfig();
    }

    /**
     * @return array<string, string>
     */
    private function buildCommonGlobalConfig(): array
    {
        $resolvedCaCertPath = $this->sslCertificateProvider->resolve($this->sslCaCertificateUrl, $this->sslCaCertificatePath);
        $resolvedClientCertPath = $this->sslCertificateProvider->resolvePem($this->sslClientCertificate, 'client_cert');
        $resolvedClientKeyPath = $this->sslCertificateProvider->resolvePem($this->sslClientKey, 'client_key');

        $hasSsl = null !== $resolvedCaCertPath || null !== $resolvedClientCertPath;
        $hasSasl = null !== $this->saslUsername && null !== $this->saslPassword;

        $securityProtocol = match (true) {
            $hasSsl && $hasSasl => 'sasl_ssl',
            $hasSasl => 'sasl_plaintext',
            $hasSsl => 'ssl',
            default => 'plaintext',
        };

        $config = [
            'metadata.broker.list' => str_replace('kafka://', '', $this->brokerUrl),
            'security.protocol' => $securityProtocol,
        ];

        if (null !== $resolvedCaCertPath) {
            $config['ssl.ca.location'] = $resolvedCaCertPath;
        }

        if (null !== $resolvedClientCertPath) {
            $config['ssl.certificate.location'] = $resolvedClientCertPath;
        }

        if (null !== $resolvedClientKeyPath) {
            $config['ssl.key.location'] = $resolvedClientKeyPath;
        }

        if (null !== $this->sslClientKeyPassword) {
            $config['ssl.key.password'] = $this->sslClientKeyPassword;
        }

        if ($hasSasl) {
            // librdkafka would default to GSSAPI, e.g. when the mechanism env var is not set.
            $config['sasl.mechanism'] = $this->saslMechanism ?? 'PLAIN';
            /** @var string $saslUsername */
            $saslUsername = $this->saslUsername;
            /** @var string $saslPassword */
            $saslPassword = $this->saslPassword;
            $config['sasl.username'] = $saslUsername;
            $config['sasl.password'] = $saslPassword;
        }

        if (null !== $this->debugLevel) {
            $config['debug'] = $this->debugLevel;
        }

        return $config;
    }

    /**
     * @return array<string, string>
     */
    private function producerConfig(): array
    {
        return [
            ...$this->commonGlobalConfig(),
            'enable.idempotence' => 'true',
            'retries' => '2147483647',
            'linger.ms' => '100',
            'batch.size' => '16384',
            'request.required.acks' => 'all',
            // send() gives up at the same time, so a Kafka outage fails the call instead of blocking it.
            'delivery.timeout.ms' => (string) $this->deliveryTimeoutMs,
            'compression.type' => 'gzip',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function consumerConfig(): array
    {
        return [
            ...$this->commonGlobalConfig(),
            'group.id' => $this->appName,
            // Offsets are committed by ConsumptionOrchestrator, once the message is processed.
            'enable.auto.commit' => 'false',
            'fetch.min.bytes' => '1000',
            'auto.offset.reset' => 'latest',
        ];
    }
}
