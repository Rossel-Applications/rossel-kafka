<?php

namespace Rossel\RosselKafkaPhpKit\Service\Consumer;

use Rossel\RosselKafkaPhpKit\Message\MessageInterface;

interface MessageConsumerInterface
{
    public function supports(MessageInterface $message): bool;

    public function consume(MessageInterface $message): void;
}
