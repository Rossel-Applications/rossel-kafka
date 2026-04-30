<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Exception\UnconfiguredTopicException;
use Rossel\RosselKafka\Exception\UnsupportedTopicException;
use Rossel\RosselKafka\Model\Topic;

final class ExceptionsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // UnconfiguredTopicException
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{TopicConfigKeys}>
     */
    public static function topicConfigKeysProvider(): iterable
    {
        yield 'log output' => [TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE];
        yield 'subscription input' => [TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE];
    }

    #[Test]
    #[DataProvider('topicConfigKeysProvider')]
    public function unconfiguredTopicExceptionMessageContainsKeyValue(TopicConfigKeys $key): void
    {
        $exception = new UnconfiguredTopicException($key);

        self::assertStringContainsString($key->value, $exception->getMessage());
    }

    #[Test]
    #[DataProvider('topicConfigKeysProvider')]
    public function unconfiguredTopicExceptionMessageContainsKeyName(TopicConfigKeys $key): void
    {
        $exception = new UnconfiguredTopicException($key);

        self::assertStringContainsString($key->name, $exception->getMessage());
    }

    #[Test]
    public function unconfiguredTopicExceptionExtendsRuntimeException(): void
    {
        $exception = new UnconfiguredTopicException(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE);

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function unconfiguredTopicExceptionMessageFormat(): void
    {
        $key = TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE;
        $exception = new UnconfiguredTopicException($key);

        self::assertStringContainsString('not configured', $exception->getMessage());
        self::assertStringContainsString('environment variable', $exception->getMessage());
    }

    // -------------------------------------------------------------------------
    // UnsupportedTopicException
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{string, MessageType}>
     */
    public static function unsupportedTopicProvider(): iterable
    {
        yield 'log topic + EXEC_SUCCESS' => ['my.log.topic', MessageType::EXEC_SUCCESS];
        yield 'sub topic + LOG' => ['my.sub.topic', MessageType::LOG];
        yield 'topic + EXEC_ERROR' => ['some.topic', MessageType::EXEC_ERROR];
    }

    #[Test]
    #[DataProvider('unsupportedTopicProvider')]
    public function unsupportedTopicExceptionMessageContainsTopicName(string $topicName, MessageType $messageType): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: $topicName,
        );

        $exception = new UnsupportedTopicException($topic, $messageType);

        self::assertStringContainsString($topicName, $exception->getMessage());
    }

    #[Test]
    #[DataProvider('unsupportedTopicProvider')]
    public function unsupportedTopicExceptionMessageContainsMessageTypeName(string $topicName, MessageType $messageType): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: $topicName,
        );

        $exception = new UnsupportedTopicException($topic, $messageType);

        self::assertStringContainsString($messageType->name, $exception->getMessage());
    }

    #[Test]
    public function unsupportedTopicExceptionExtendsRuntimeException(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'test.topic',
        );

        $exception = new UnsupportedTopicException($topic, MessageType::LOG);

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function unsupportedTopicExceptionMessageFormat(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'test.topic',
        );

        $exception = new UnsupportedTopicException($topic, MessageType::LOG);

        self::assertStringContainsString('not supported', $exception->getMessage());
    }
}
