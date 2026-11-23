<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Service\Connector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RdKafka\Producer;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Enum\Topic\TopicDirection;
use Rossel\RosselKafka\Exception\MessageNotDeliveredException;
use Rossel\RosselKafka\Exception\UnauthorizedTopicOperationException;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;
use Rossel\RosselKafka\Service\Ssl\SslCertificateProvider;

final class KafkaConnectorTest extends TestCase
{
    // Nothing listens on port 1: no message can be delivered.
    private const UNREACHABLE_BROKER = 'kafka://127.0.0.1:1';

    #[Test]
    public function shouldNotCreateProducerOnConstruction(): void
    {
        $connector = $this->buildConnector();

        $producer = $this->producerOf($connector);

        self::assertNull($producer);
    }

    #[Test]
    public function shouldNotCreateProducerWhenSendingToNonProducibleTopic(): void
    {
        $connector = $this->buildConnector();
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output',
        );
        $exception = null;

        try {
            $connector->send($topic, $this->buildMessage());
        } catch (UnauthorizedTopicOperationException $e) {
            $exception = $e;
        }

        self::assertInstanceOf(UnauthorizedTopicOperationException::class, $exception);
        self::assertNull($this->producerOf($connector));
    }

    #[Test]
    public function shouldThrowWhenTheBrokerDoesNotConfirmDelivery(): void
    {
        $connector = $this->buildConnector(brokerUrl: self::UNREACHABLE_BROKER, deliveryTimeoutMs: 500);

        $this->expectException(MessageNotDeliveredException::class);
        $this->expectExceptionMessage('Local: Message timed out');

        $connector->send($this->buildProducibleTopic(), $this->buildMessage());
    }

    #[Test]
    public function shouldLeaveNothingQueuedWhenDeliveryFails(): void
    {
        $connector = $this->buildConnector(brokerUrl: self::UNREACHABLE_BROKER, deliveryTimeoutMs: 500);

        try {
            $connector->send($this->buildProducibleTopic(), $this->buildMessage());
        } catch (MessageNotDeliveredException) {
        }

        $producer = $this->producerOf($connector);
        self::assertInstanceOf(Producer::class, $producer);
        self::assertSame(0, $producer->getOutQLen());
    }

    #[Test]
    public function shouldNotResolveSslCertificateOnConstruction(): void
    {
        $connector = null;
        $exception = null;

        try {
            $connector = $this->buildConnector(sslCaCertificatePath: '/nonexistent/ca.pem');
        } catch (\RuntimeException $e) {
            $exception = $e;
        }

        self::assertNull($exception);
        self::assertInstanceOf(KafkaConnector::class, $connector);
    }

    #[Test]
    public function shouldResolveSslCertificateOnFirstConsumerCreation(): void
    {
        $connector = $this->buildConnector(sslCaCertificatePath: '/nonexistent/ca.pem');
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output',
        );
        $exception = null;

        try {
            $connector->createConsumer($topic);
        } catch (\RuntimeException $e) {
            $exception = $e;
        }

        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertStringContainsString('/nonexistent/ca.pem', $exception->getMessage());
    }

    #[Test]
    public function shouldUsePlainSaslMechanismWhenMechanismIsNull(): void
    {
        $connector = $this->buildConnector(saslUsername: 'user', saslPassword: 'secret');

        $globalConfig = $this->consumerGlobalConfigOf($connector);

        self::assertSame('PLAIN', $globalConfig['sasl.mechanism']);
    }

    #[Test]
    public function shouldCommitOffsetsOnlyOnAcknowledge(): void
    {
        $connector = $this->buildConnector();

        $globalConfig = $this->consumerGlobalConfigOf($connector);

        self::assertSame('false', $globalConfig['enable.auto.commit']);
        self::assertArrayNotHasKey('auto.commit.interval.ms', $globalConfig);
    }

    private function buildConnector(
        ?string $sslCaCertificatePath = null,
        ?string $saslUsername = null,
        ?string $saslPassword = null,
        string $brokerUrl = 'kafka://localhost:9092',
        int $deliveryTimeoutMs = 30_000,
    ): KafkaConnector {
        return new KafkaConnector(
            brokerUrl: $brokerUrl,
            appName: 'test-app',
            saslUsername: $saslUsername,
            saslPassword: $saslPassword,
            saslMechanism: null,
            sslCaCertificateUrl: null,
            sslCaCertificatePath: $sslCaCertificatePath,
            sslClientCertificate: null,
            sslClientKey: null,
            sslClientKeyPassword: null,
            sslCertificateProvider: new SslCertificateProvider(),
            deliveryTimeoutMs: $deliveryTimeoutMs,
        );
    }

    private function buildProducibleTopic(): Topic
    {
        return new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output',
            direction: TopicDirection::PRODUCE,
        );
    }

    private function buildMessage(): Message
    {
        return new Message(
            new MessageHeaders(
                area: Area::FRANCE,
                from: 'test-app',
                messageType: MessageType::LOG,
            ),
            'body',
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function consumerGlobalConfigOf(KafkaConnector $connector): array
    {
        $config = (new \ReflectionMethod($connector, 'consumerConfig'))->invoke($connector);
        self::assertIsArray($config);

        return $config;
    }

    private function producerOf(KafkaConnector $connector): mixed
    {
        return (new \ReflectionProperty($connector, 'rdKafkaProducer'))->getValue($connector);
    }
}
