<?php

namespace Rossel\RosselKafka\Orchestrator;

use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;

class ConsumptionOrchestrator
{
    // private array $consumers = [];

    /**
     * @param iterable<ConsumerInterface> $consumers
     */
    public function __construct(
        //readonly iterable $consumers,
        //private KafkaConnector $kafkaConnector,
    ) {
    }

    public function listen(
        // callbacks
    ): void
    {
        //while (true) {

        //}
    }

    private function defineConsumers(): void
    {
        //foreach ($this->consumers as $consumer) {

        //}
    }
}
