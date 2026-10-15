<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Service\Connector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Exception\UnauthorizedTopicOperationException;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;
use Rossel\RosselKafka\Service\Ssl\SslCertificateProvider;

final class KafkaConnectorTest extends TestCase
{
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
    ): KafkaConnector {
        return new KafkaConnector(
            brokerUrl: 'kafka://localhost:9092',
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
     * @return array<string, string>
     */
    private function consumerGlobalConfigOf(KafkaConnector $connector): array
    {
        $context = (new \ReflectionMethod($connector, 'consumerContext'))->invoke($connector);
        self::assertIsObject($context);
        /** @var array{global: array<string, string>} $config */
        $config = (new \ReflectionProperty($context, 'config'))->getValue($context);

        return $config['global'];
    }

    private function producerOf(KafkaConnector $connector): mixed
    {
        return (new \ReflectionProperty($connector, 'rdKafkaProducer'))->getValue($connector);
    }
}
