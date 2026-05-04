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
use Rossel\RosselKafka\Service\Ssl\SslCertificateProvider;

final class KafkaConnector implements KafkaConnectorInterface
{
    private readonly RdKafkaContext $rdKafkaProducerContext;

    private readonly RdKafkaContext $rdKafkaConsumerContext;

    private readonly RdKafkaProducer $rdKafkaProducer;

    /** @var array<string, RdKafkaTopic> */
    private array $producerTopics = [];

    /** @var array<string, RdKafkaTopic> */
    private array $consumerTopics = [];

    public function __construct(
        string $brokerUrl,
        private readonly string $appName,
        private readonly ?string $saslUsername,
        private readonly ?string $saslPassword,
        private readonly ?string $saslMechanism,
        ?string $sslCaCertificateUrl,
        ?string $sslCaCertificatePath,
        ?string $sslClientCertificate,
        ?string $sslClientKey,
        private readonly ?string $sslClientKeyPassword,
        SslCertificateProvider $sslCertificateProvider,
        private readonly ?string $debugLevel = null,
    ) {
        $resolvedCaCertPath = $sslCertificateProvider->resolve($sslCaCertificateUrl, $sslCaCertificatePath);
        $resolvedClientCertPath = $sslCertificateProvider->resolvePem($sslClientCertificate, 'client_cert');
        $resolvedClientKeyPath = $sslCertificateProvider->resolvePem($sslClientKey, 'client_key');

        $cleanBrokerUrl = str_replace('kafka://', '', $brokerUrl);

        $this->rdKafkaProducerContext = $this->buildProducerContext($cleanBrokerUrl, $resolvedCaCertPath, $resolvedClientCertPath, $resolvedClientKeyPath);
        $this->rdKafkaConsumerContext = $this->buildConsumerContext($cleanBrokerUrl, $resolvedCaCertPath, $resolvedClientCertPath, $resolvedClientKeyPath);
        $this->rdKafkaProducer = $this->rdKafkaProducerContext->createProducer();
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

        return $this->rdKafkaConsumerContext->createConsumer($this->getConsumerTopic($topic));
    }

    private function getProducerTopic(Topic $topic): RdKafkaTopic
    {
        return $this->producerTopics[$topic->getName()] ??= $this->rdKafkaProducerContext->createTopic($topic->getName());
    }

    private function getConsumerTopic(Topic $topic): RdKafkaTopic
    {
        return $this->consumerTopics[$topic->getName()] ??= $this->rdKafkaConsumerContext->createTopic($topic->getName());
    }

    /**
     * @return array<string, string>
     */
    private function buildCommonGlobalConfig(string $brokerUrl, ?string $resolvedCaCertPath, ?string $resolvedClientCertPath, ?string $resolvedClientKeyPath): array
    {
        $hasSsl = null !== $resolvedCaCertPath || null !== $resolvedClientCertPath;
        $hasSasl = null !== $this->saslUsername && null !== $this->saslPassword;

        $securityProtocol = match (true) {
            $hasSsl && $hasSasl => 'sasl_ssl',
            $hasSasl => 'sasl_plaintext',
            $hasSsl => 'ssl',
            default => 'plaintext',
        };

        $config = [
            'metadata.broker.list' => $brokerUrl,
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
            if (null !== $this->saslMechanism) {
                $config['sasl.mechanism'] = $this->saslMechanism;
            }
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

    private function buildProducerContext(string $brokerUrl, ?string $resolvedCaCertPath, ?string $resolvedClientCertPath, ?string $resolvedClientKeyPath): RdKafkaContext
    {
        /** @var array<string, string> $globalConfig */
        $globalConfig = array_merge(
            $this->buildCommonGlobalConfig($brokerUrl, $resolvedCaCertPath, $resolvedClientCertPath, $resolvedClientKeyPath),
            [
                'enable.idempotence' => 'true',
                'retries' => '2147483647',
                'linger.ms' => '100',
                'batch.size' => '16384',
            ]
        );

        return new RdKafkaContext([
            'global' => $globalConfig,
            'topic' => [
                'request.required.acks' => 'all',
                'delivery.timeout.ms' => '518400000', // 6 days
                'compression.type' => 'gzip',
            ],
        ]);
    }

    private function buildConsumerContext(string $brokerUrl, ?string $resolvedCaCertPath, ?string $resolvedClientCertPath, ?string $resolvedClientKeyPath): RdKafkaContext
    {
        /** @var array<string, string> $globalConfig */
        $globalConfig = array_merge(
            $this->buildCommonGlobalConfig($brokerUrl, $resolvedCaCertPath, $resolvedClientCertPath, $resolvedClientKeyPath),
            [
                'group.id' => $this->appName,
                'enable.auto.commit' => 'true',
                'auto.commit.interval.ms' => '5000',
                'fetch.min.bytes' => '1000',
            ]
        );

        return new RdKafkaContext([
            'global' => $globalConfig,
            'topic' => [
                'auto.offset.reset' => 'latest',
            ],
        ]);
    }
}
