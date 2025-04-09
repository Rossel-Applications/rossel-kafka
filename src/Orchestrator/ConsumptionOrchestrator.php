<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Orchestrator;

use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;

class ConsumptionOrchestrator
{
    // private array $consumers = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        // readonly iterable $consumers,
        // private KafkaConnector $kafkaConnector,
    ) {
    }

    public function listen(
        KafkaTopic $topic,
        ?\Closure $onStartCallable = null,
    ): void {
        if (null !== $onStartCallable) {
            $onStartCallable($topic);
        }

        $this->logger->info(\sprintf('Listening on topic %s started.', $topic->name));

        // while (true) {

        // }
    }

    private function defineConsumers(): void
    {
        // foreach ($this->consumers as $consumer) {

        // }
    }
}
