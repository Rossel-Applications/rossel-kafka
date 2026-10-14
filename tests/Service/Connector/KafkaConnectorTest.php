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

    private function buildConnector(): KafkaConnector
    {
        return new KafkaConnector(
            brokerUrl: 'kafka://localhost:9092',
            appName: 'test-app',
            saslUsername: null,
            saslPassword: null,
            saslMechanism: null,
            sslCaCertificateUrl: null,
            sslCaCertificatePath: null,
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

    private function producerOf(KafkaConnector $connector): mixed
    {
        return (new \ReflectionProperty($connector, 'rdKafkaProducer'))->getValue($connector);
    }
}
