<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Factory;

use Enqueue\RdKafka\RdKafkaMessage;
use Rossel\RosselKafka\Model\Message;

interface MessageFactoryInterface
{
    /**
     * @throws \JsonException
     * @throws \Exception
     */
    public function createMessageFromRdKafka(RdKafkaMessage $message): Message;
}
