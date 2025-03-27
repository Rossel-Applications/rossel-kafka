<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Service\Connector;

use Enqueue\RdKafka\RdKafkaTopic;
use Rossel\RosselKafkaPhpKit\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafkaPhpKit\Model\MessageInterface;

interface KafkaConnectorInterface
{
    public function send(KafkaTopic|RdKafkaTopic $topic, MessageInterface $message): void;
}
