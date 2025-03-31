<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Consumer;

use Rossel\RosselKafka\Model\MessageInterface;

interface MessageConsumerInterface
{
    public function supports(MessageInterface $message): bool;

    public function consume(MessageInterface $message): void;
}
