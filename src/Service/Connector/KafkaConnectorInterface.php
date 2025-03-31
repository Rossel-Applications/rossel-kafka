<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Connector;

use Enqueue\RdKafka\RdKafkaTopic;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Model\MessageInterface;

interface KafkaConnectorInterface
{
    public function send(KafkaTopic|RdKafkaTopic $topic, MessageInterface $message): void;
}
