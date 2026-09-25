<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Consumer;

use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\Topic;

interface ConsumerInterface
{
    /**
     * Checks whether the given topic is supported by the consumer.
     *
     * @param Topic $topic the Kafka topic to check
     *
     * @return bool true if the topic is supported, false otherwise
     */
    public function supportsTopic(Topic $topic): bool;

    /**
     * Checks whether the given message type is supported by the consumer.
     *
     * @param Message $message the message to check
     *
     * @return bool true if the message type is supported, false otherwise
     */
    public function supportsMessageType(Message $message): bool;

    /**
     * Handles the consumption of a supported message.
     *
     * @param Message $message the message to consume
     */
    public function __invoke(Message $message): void;
}
