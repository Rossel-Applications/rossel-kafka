<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Exception;

use Rossel\RosselKafka\Model\Topic;

/**
 * The broker did not acknowledge the message. It may still have been written (an in-flight request can succeed
 * after the timeout): resending it is at-least-once, never at-most-once.
 */
final class MessageNotDeliveredException extends \RuntimeException
{
    public static function to(Topic $topic, string $reason): self
    {
        return new self(\sprintf('Message to topic "%s" was not delivered: %s', $topic->getName(), $reason));
    }
}
