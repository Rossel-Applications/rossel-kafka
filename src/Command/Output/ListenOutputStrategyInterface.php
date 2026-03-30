<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Command\Output;

use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;

interface ListenOutputStrategyInterface
{
    public function onStart(Topic $topic): void;

    public function onIdle(Topic $topic): void;

    public function onMessage(Topic $topic, MessageType $messageType): void;

    public function warning(string $message): void;
}
