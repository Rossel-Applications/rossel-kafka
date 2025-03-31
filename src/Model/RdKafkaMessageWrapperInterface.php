<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Enqueue\RdKafka\RdKafkaMessage;

/**
 * Interface to be implemented by classes that must provide access to a `Enqueue\RdKafka\RdKafkaMessage` object.
 */
interface RdKafkaMessageWrapperInterface
{
    public function getRdKafkaMessage(): RdKafkaMessage;
}
