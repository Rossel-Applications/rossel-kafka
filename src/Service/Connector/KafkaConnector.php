<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Connector;

use Enqueue\RdKafka\RdKafkaConsumer;
use Enqueue\RdKafka\RdKafkaContext;
use Enqueue\RdKafka\RdKafkaProducer;
use Enqueue\RdKafka\RdKafkaTopic;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Rossel\RosselKafka\Exception\UnauthorizedTopicOperationException;
use Rossel\RosselKafka\Model\MessageInterface;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\Serializer\RawKafkaSerializer;
use Rossel\RosselKafka\Service\Ssl\SslCertificateProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class KafkaConnector implements KafkaConnectorInterface
{
    private ?RdKafkaContext $rdKafkaProducerContext = null;

    private ?RdKafkaContext $rdKafkaConsumerContext = null;

    /** @var array<string, string>|null */
    private ?array $commonGlobalConfig = null;

    private ?RdKafkaProducer $rdKafkaProducer = null;

    /** @var array<string, RdKafkaTopic> */
    private array $producerTopics = [];

    /** @var array<string, RdKafkaTopic> */
    private array $consumerTopics = [];

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
    ) {
    }

    /**
     * Send a message to a Kafka topic.
     *
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws Exception
     */
    public function send(Topic|RdKafkaTopic $topic, MessageInterface $message): void
    {
        if ($topic instanceof Topic) {
            if (!$topic->isProducible()) {
                throw UnauthorizedTopicOperationException::produce($topic);
            }

            $topic = $this->getProducerTopic($topic);
        }

        // Created on first send, so consumer-only apps never open a producer.
        $this->rdKafkaProducer ??= $this->producerContext()->createProducer();
        $this->rdKafkaProducer->send(
            $topic,
            $message->getRdKafkaMessage(),
        );
    }

    /**
     * Create a consumer object, which is responsible for listening messages published on a topic.
     */
    public function createConsumer(Topic $topic): RdKafkaConsumer
    {
        if (!$topic->isConsumable()) {
            throw UnauthorizedTopicOperationException::consume($topic);
        }

        return $this->consumerContext()->createConsumer($this->getConsumerTopic($topic));
    }

    private function getProducerTopic(Topic $topic): RdKafkaTopic
    {
        return $this->producerTopics[$topic->getName()] ??= $this->producerContext()->createTopic($topic->getName());
    }

    private function getConsumerTopic(Topic $topic): RdKafkaTopic
    {
        return $this->consumerTopics[$topic->getName()] ??= $this->consumerContext()->createTopic($topic->getName());
    }

    // Built on first use, so the SSL certificates are only resolved (and downloaded) when a connection is needed.
    private function producerContext(): RdKafkaContext
    {
        return $this->rdKafkaProducerContext ??= $this->buildProducerContext();
    }

    private function consumerContext(): RdKafkaContext
    {
        return $this->rdKafkaConsumerContext ??= $this->buildConsumerContext();
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

    private function buildProducerContext(): RdKafkaContext
    {
        /** @var array<string, string> $globalConfig */
        $globalConfig = array_merge(
            $this->commonGlobalConfig(),
            [
                'enable.idempotence' => 'true',
                'retries' => '2147483647',
                'linger.ms' => '100',
                'batch.size' => '16384',
            ]
        );

        $context = new RdKafkaContext([
            'global' => $globalConfig,
            'topic' => [
                'request.required.acks' => 'all',
                'delivery.timeout.ms' => '518400000', // 6 days
                'compression.type' => 'gzip',
            ],
        ]);

        // Align with the Rossel Kafka cluster contract: value carries the raw
        // business payload, metadata travels in real Kafka headers.
        $context->setSerializer(new RawKafkaSerializer());

        return $context;
    }

    private function buildConsumerContext(): RdKafkaContext
    {
        /** @var array<string, string> $globalConfig */
        $globalConfig = array_merge(
            $this->commonGlobalConfig(),
            [
                'group.id' => $this->appName,
                // Offsets are committed by acknowledge(), once the message is processed.
                'enable.auto.commit' => 'false',
                'fetch.min.bytes' => '1000',
            ]
        );

        $context = new RdKafkaContext([
            'global' => $globalConfig,
            'topic' => [
                'auto.offset.reset' => 'latest',
            ],
        ]);

        // Align with the Rossel Kafka cluster contract: value carries the raw
        // business payload, metadata travels in real Kafka headers.
        $context->setSerializer(new RawKafkaSerializer());

        return $context;
    }
}
