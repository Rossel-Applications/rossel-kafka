<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Orchestrator;

use Enqueue\RdKafka\RdKafkaMessage;
use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Factory\MessageFactory;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;

class ConsumptionOrchestrator
{
    // private array $consumers = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        // readonly iterable $consumers,
        private readonly KafkaConnector $kafkaConnector,
        private readonly MessageFactory $messageFactory,
    ) {
    }

    public function listen(
        KafkaTopic $topic,
        ?\Closure $onStartCallable = null,
    ): void {
        if (null !== $onStartCallable) {
            $this->logger->debug('Executing onStartCallable before consumer creation.');
            $onStartCallable($topic);
        }

        $this->logger->info(\sprintf('Initializing Kafka consumer for topic "%s"...', $topic->name));

        $this->logger->debug(\sprintf('Creating consumer for topic %s...', $topic->name));
        $consumer = $this->kafkaConnector->createConsumer($topic);
        $this->logger->debug(\sprintf('Consumer for topic %s successfully created.', $topic->name));

        $this->logger->info(\sprintf('Consumer is now listening on topic "%s".', $topic->name));

        while (true) {
            $message = $consumer->receive(1000);

            if ($message instanceof RdKafkaMessage) {
                $this->logger->info(
                    'Message received from consumer.',
                    [
                        'id' => $message->getMessageId(),
                    ],
                );

                $rosselMessage = $this->messageFactory->createMessageFromRdKafka($message);
                dd($rosselMessage);
                $this->processRosselMessage($rosselMessage);

                // Marque le message comme "traité"
                $consumer->acknowledge($message);
            }
        }
    }

    private function processRosselMessage(RdKafkaMessage $message): void
    {
        $this->logger->debug(
            'Processing message...',
            [
                'id' => $message->getMessageId(),
            ],
        );
    }
}
