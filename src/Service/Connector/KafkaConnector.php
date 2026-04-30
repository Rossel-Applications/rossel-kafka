<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Connector;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Enqueue\RdKafka\RdKafkaConsumer;
use Enqueue\RdKafka\RdKafkaContext;
use Enqueue\RdKafka\RdKafkaProducer;
use Enqueue\RdKafka\RdKafkaTopic;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Rossel\RosselKafka\Model\MessageInterface;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\Ssl\SslCertificateProvider;

final class KafkaConnector implements KafkaConnectorInterface
{
    private readonly RdKafkaContext $rdKafkaContext;

    private readonly RdKafkaProducer $rdKafkaProducer;

    /** @var array<string, RdKafkaTopic> */
    private array $rdKafkaTopics = [];

    public function __construct(
        string $brokerUrl,
        private string $appName,
        private ?string $saslUsername,
        private ?string $saslPassword,
        private ?string $saslMechanism,
        ?string $sslCaCertificateUrl,
        ?string $sslCaCertificatePath,
        ?string $sslClientCertificate,
        ?string $sslClientKey,
        private ?string $sslClientKeyPassword,
        SslCertificateProvider $sslCertificateProvider,
    ) {
        $resolvedCaCertPath = $sslCertificateProvider->resolve($sslCaCertificateUrl, $sslCaCertificatePath);
        $resolvedClientCertPath = $sslCertificateProvider->resolvePem($sslClientCertificate, 'client_cert');
        $resolvedClientKeyPath = $sslCertificateProvider->resolvePem($sslClientKey, 'client_key');
        $this->rdKafkaContext = $this->buildContext($brokerUrl, $resolvedCaCertPath, $resolvedClientCertPath, $resolvedClientKeyPath);
        $this->rdKafkaProducer = $this->rdKafkaContext->createProducer();
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
            $topic = $this->getRdKafkaTopic($topic);
        }

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
        return $this->rdKafkaContext->createConsumer($this->getRdKafkaTopic($topic));
    }

    private function getRdKafkaTopic(Topic $topic): RdKafkaTopic
    {
        return $this->rdKafkaTopics[$topic->getName()] ??= $this->rdKafkaContext->createTopic($topic->getName());
    }

    private function buildContext(string $brokerUrl, ?string $resolvedCaCertPath, ?string $resolvedClientCertPath, ?string $resolvedClientKeyPath): RdKafkaContext
    {
        return $this->buildConnectionFactory($brokerUrl, $resolvedCaCertPath, $resolvedClientCertPath, $resolvedClientKeyPath)->createContext();
    }

    private function buildConnectionFactory(string $brokerUrl, ?string $resolvedCaCertPath, ?string $resolvedClientCertPath, ?string $resolvedClientKeyPath): RdKafkaConnectionFactory
    {
        $brokerUrl = str_replace('kafka://', '', $brokerUrl);

        $hasSsl = null !== $resolvedCaCertPath || null !== $resolvedClientCertPath;
        $hasSasl = null !== $this->saslUsername && null !== $this->saslPassword;

        $securityProtocol = match (true) {
            $hasSsl && $hasSasl => 'sasl_ssl',
            $hasSasl => 'sasl_plaintext',
            $hasSsl => 'ssl',
            default => 'plaintext',
        };

        $globalConfig = [
            'group.id' => $this->appName,
            'metadata.broker.list' => $brokerUrl,
            'enable.auto.commit' => 'true',
            'auto.commit.interval.ms' => '5000',
            'enable.idempotence' => 'true',
            'retries' => '2147483647',
            'linger.ms' => '100',
            'batch.size' => '16384',
            'fetch.min.bytes' => '1000',
            'security.protocol' => $securityProtocol,
        ];

        if (null !== $resolvedCaCertPath) {
            $globalConfig['ssl.ca.location'] = $resolvedCaCertPath;
        }

        if (null !== $resolvedClientCertPath) {
            $globalConfig['ssl.certificate.location'] = $resolvedClientCertPath;
        }

        if (null !== $resolvedClientKeyPath) {
            $globalConfig['ssl.key.location'] = $resolvedClientKeyPath;
        }

        if (null !== $this->sslClientKeyPassword) {
            $globalConfig['ssl.key.password'] = $this->sslClientKeyPassword;
        }

        if ($hasSasl) {
            $globalConfig['sasl.mechanism'] = $this->saslMechanism;
            $globalConfig['sasl.username'] = $this->saslUsername;
            $globalConfig['sasl.password'] = $this->saslPassword;
        }

        return new RdKafkaConnectionFactory([
            'global' => $globalConfig,
            'topic' => [
                'auto.offset.reset' => 'latest',
                'request.required.acks' => 'all',
                'delivery.timeout.ms' => '518400000', // 6 days
                'compression.type' => 'gzip',
            ],
        ]);
    }
}
