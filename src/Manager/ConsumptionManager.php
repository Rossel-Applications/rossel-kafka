<?php

namespace Rossel\RosselKafka\Manager;

use Rossel\RosselKafka\Consumer\ConsumerInterface;

class ConsumptionManager
{
    /**
     * @param iterable<ConsumerInterface> $consumers
     */
    public function __construct(
        private readonly iterable $consumers
    ) {
    }

    public function loop(): void
    {

    }
}
