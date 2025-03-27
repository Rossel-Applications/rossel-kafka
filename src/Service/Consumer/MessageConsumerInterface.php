<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Service\Consumer;

use Rossel\RosselKafkaPhpKit\Model\MessageInterface;

interface MessageConsumerInterface
{
    public function supports(MessageInterface $message): bool;

    public function consume(MessageInterface $message): void;
}
