<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service;

use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;

interface KafkaTopicsFetcherInterface
{
    public function get(TopicConfigKeys $key): Topic;

    /**
     * @return array<string, Topic>
     */
    public function getAll(): array;

    /**
     * @return array<array-key, Topic>
     */
    public function getByMessageType(MessageType $messageType): array;
}
