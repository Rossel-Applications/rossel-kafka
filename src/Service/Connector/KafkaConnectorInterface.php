<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Connector;

use RdKafka\KafkaConsumer;
use Rossel\RosselKafka\Model\MessageInterface;
use Rossel\RosselKafka\Model\Topic;

interface KafkaConnectorInterface
{
    public function send(Topic $topic, MessageInterface $message): void;

    /**
     * Returns a consumer already subscribed to the topic.
     */
    public function createConsumer(Topic $topic): KafkaConsumer;
}
