<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Orchestrator;

use Enqueue\RdKafka\RdKafkaMessage;
use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Factory\MessageFactory;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;

readonly class ConsumptionOrchestrator
{
    /**
     * @param iterable<ConsumerInterface> $consumers
     */
    public function __construct(
        private LoggerInterface $logger,
        private KafkaConnector $kafkaConnector,
        private MessageFactory $messageFactory,
        private iterable $consumers = [],
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

                $this->processRosselMessage($rosselMessage, $topic);

                // Marque le message comme "traité"
                $consumer->acknowledge($message);
            }
        }
    }

    private function processRosselMessage(
        Message $message,
        KafkaTopic $topic,
    ): void {
        $messageId = $message->getRdKafkaMessage()->getMessageId();

        $this->logger->debug(
            \sprintf('Processing message #%s...', $messageId),
            [
                'id' => $messageId,
            ],
        );

        foreach ($this->consumers as $consumer) {
            if (true === $this->tryConsumer($consumer, $message, $topic)) {
                return;
            }
        }

        $this->logger->error(
            \sprintf(
                'No consumer found for message type %s and topic %s',
                $message->getType()->name,
                $topic->name,
            ),
            [
                'id' => $messageId,
                'topic' => $topic->name,
                'type' => $message->getType()->name,
            ]
        );
    }

    private function tryConsumer(
        ConsumerInterface $consumer,
        Message $message,
        KafkaTopic $topic,
    ): bool {
        $messageId = $message->getRdKafkaMessage()->getMessageId();

        if (($supportsMessageType = $consumer->supportsMessageType($message))
            && ($supportsTopic = $consumer->supportsTopic($topic))
        ) {
            $consumer($message);

            $this->logger->info(
                \sprintf(
                    'Message #%s consumed by %s',
                    $messageId,
                    $consumer::class,
                ),
                [
                    'id' => $messageId,
                    'consumer' => $consumer::class,
                ]
            );

            return true;
        }

        $reasons = [];

        if (false === $supportsMessageType) {
            $reasons['supportsMessageType'] = false;
        }

        if (isset($supportsTopic) && false === $supportsTopic) {
            $reasons['supportsTopic'] = false;
        }

        $this->logger->debug(
            \sprintf(
                'Message #%s doesn\'t satisfy the requirements to be consumed by the %s consumer.',
                $messageId,
                $consumer::class,
            ),
            [
                'id' => $messageId,
                'consumer' => $consumer::class,
                'reasons' => $reasons,
            ]
        );

        return false;
    }
}
