<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Exception;

use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;

final class UnsupportedTopicException extends \RuntimeException
{
    private const EXCEPTION_MESSAGE = 'The topic "%s" is not supported by message type "%s".';

    public function __construct(
        Topic $topic,
        MessageType $messageType,
    ) {
        parent::__construct(
            \sprintf(self::EXCEPTION_MESSAGE, $topic->getName(), $messageType->name),
        );
    }
}
