<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Exception;

use Rossel\RosselKafka\Model\Topic;

final class UnauthorizedTopicOperationException extends \RuntimeException
{
    private const EXCEPTION_MESSAGE = 'Topic "%s" (direction: %s) does not support %s.';

    public static function consume(Topic $topic): self
    {
        return new self(\sprintf(
            self::EXCEPTION_MESSAGE,
            $topic->getName(),
            $topic->getDirection()->name,
            'consumption',
        ));
    }

    public static function produce(Topic $topic): self
    {
        return new self(\sprintf(
            self::EXCEPTION_MESSAGE,
            $topic->getName(),
            $topic->getDirection()->name,
            'production',
        ));
    }
}
