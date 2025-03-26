<?php

namespace Rossel\RosselKafkaPhpKit\Service\Dispatcher;

use Rossel\RosselKafkaPhpKit\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafkaPhpKit\Message\MessageInterface;

interface MessageDispatcherInterface
{
    /**
     * Dispatches a message to the specified Kafka topics.
     *
     * @param MessageInterface $message The message to be dispatched.
     * @param array<array-key, KafkaTopic> $topics An array of topic where the message will be sent.
     */
    public function dispatch(
        MessageInterface $message,
        array $topics,
    ): void;
}
