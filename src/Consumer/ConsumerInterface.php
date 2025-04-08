<?php

namespace Rossel\RosselKafka\Consumer;

use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

interface ConsumerInterface
{
    public function supportsTopic(KafkaTopic $topic): bool;

    public function supportsMessageType(MessageType $messageType): bool;

    public function __invoke();
}
