<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Exception\UnconfiguredTopicException;

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
        yield 'log output' => [TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE];
        yield 'subscription input' => [TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE];
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
        $exception = new UnconfiguredTopicException(TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE);

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function unconfiguredTopicExceptionMessageFormat(): void
    {
        $key = TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE;
        $exception = new UnconfiguredTopicException($key);

        self::assertStringContainsString('not configured', $exception->getMessage());
        self::assertStringContainsString('environment variable', $exception->getMessage());
    }
}
