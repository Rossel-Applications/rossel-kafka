<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Consumer;

use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Model\Message;

interface ConsumerInterface
{
    /**
     * Checks whether the given topic is supported by the consumer.
     *
     * @param KafkaTopic $topic the Kafka topic to check
     *
     * @return bool true if the topic is supported, false otherwise
     */
    public function supportsTopic(KafkaTopic $topic): bool;

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
     *
     * @return void
     */
    public function __invoke(Message $message): void;
}
