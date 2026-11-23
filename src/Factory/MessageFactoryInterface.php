<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Factory;

use RdKafka\Message as RdKafkaMessage;
use Rossel\RosselKafka\Model\Message;

interface MessageFactoryInterface
{
    /**
     * @throws \Exception
     */
    public function createMessageFromRdKafka(RdKafkaMessage $message): Message;
}
