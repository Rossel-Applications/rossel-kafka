<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

/**
 * Interface to be implemented by classes representing Kafka messages.
 */
interface MessageInterface extends RdKafkaMessageWrapperInterface
{
    /**
     * Returns the type of the message.
     *
     * @return MessageType the type of the message
     */
    public function getType(): MessageType;
}
