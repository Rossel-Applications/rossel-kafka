<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Connector;

use Enqueue\RdKafka\RdKafkaTopic;
use Rossel\RosselKafka\Model\MessageInterface;
use Rossel\RosselKafka\Model\Topic;

interface KafkaConnectorInterface
{
    public function send(Topic|RdKafkaTopic $topic, MessageInterface $message): void;
}
