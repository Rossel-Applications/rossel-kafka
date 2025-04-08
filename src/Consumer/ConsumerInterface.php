<?php

namespace Rossel\RosselKafka\Consumer;

use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

interface ConsumerInterface
{
    public const CONSUME_SUCCESS = false;
    public const CONSUME_ERROR = true;

    /**
     * @return array<array-key, KafkaTopic>
     */
    public function getSubscribedTopics(): array;

    /**
     * @return array<array-key, MessageType>
     */
    public function getSubscribedMessageTypes(): array;

    public function consume(): bool;
}
