<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Exception;

use Rossel\RosselKafkaPhpKit\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafkaPhpKit\Enum\MessageHeaders\MessageType;

final class UnsupportedTopicException extends \RuntimeException
{
    private const EXCEPTION_MESSAGE = 'The topic "%s" is not supported by message type "%s".';

    public function __construct(
        KafkaTopic $topic,
        MessageType $messageType,
    ) {
        parent::__construct(
            \sprintf(self::EXCEPTION_MESSAGE, $topic->name, $messageType->name),
        );
    }
}
