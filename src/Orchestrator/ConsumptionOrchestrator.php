<?php

namespace Rossel\RosselKafka\Orchestrator;

use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
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
        KafkaTopic $topic,
        ?callable $onStartCallable = null,
    ): void
    {
        if (null !== $onStartCallable) {
            $onStartCallable($topic);
        }

        //while (true) {

        //}
    }

    private function defineConsumers(): void
    {
        //foreach ($this->consumers as $consumer) {

        //}
    }
}
