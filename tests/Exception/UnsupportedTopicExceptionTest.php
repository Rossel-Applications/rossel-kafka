<?php

namespace Exception;

use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Exception\UnsupportedTopicException;

final class UnsupportedTopicExceptionTest extends TestCase
{
    /**
     * Test that the exception message is correctly formatted
     * based on the provided KafkaTopic and MessageType.
     */
    public function testExceptionMessageIsFormattedCorrectly(): void
    {
        $topic = KafkaTopic::cases()[0];
        $messageType = MessageType::cases()[0];

        $exception = new UnsupportedTopicException($topic, $messageType);

        $expectedMessage = sprintf(
            'The topic "%s" is not supported by message type "%s".',
            $topic->name,
            $messageType->name
        );

        self::assertSame($expectedMessage, $exception->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }
}
