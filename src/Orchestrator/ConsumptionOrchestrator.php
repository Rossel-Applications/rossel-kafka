<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Orchestrator;

use Enqueue\RdKafka\RdKafkaMessage;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Factory\MessageFactoryInterface;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\Connector\KafkaConnectorInterface;

final readonly class ConsumptionOrchestrator
{
    /**
     * @param iterable<ConsumerInterface> $consumers
     */
    public function __construct(
        private LoggerInterface $logger,
        private KafkaConnectorInterface $kafkaConnector,
        private MessageFactoryInterface $messageFactory,
        private iterable $consumers,
        private string $appName,
    ) {
    }

    public function listen(
        Topic $topic,
        ?\Closure $onStartCallable = null,
        ?\Closure $onIdleCallable = null,
        ?\Closure $onMessageCallable = null,
    ): void {
        if (null !== $onStartCallable) {
            $this->logger->debug('Executing onStartCallable before consumer creation.');
            $onStartCallable($topic);
        }

        $this->logger->info(\sprintf('Initializing Kafka consumer for topic "%s"...', $topic->getName()));

        $this->logger->debug(\sprintf('Creating consumer for topic %s...', $topic->getName()));
        $consumer = $this->kafkaConnector->createConsumer($topic);
        $this->logger->debug(\sprintf('Consumer for topic %s successfully created.', $topic->getName()));

        $this->logger->info(\sprintf('Consumer is now listening on topic "%s".', $topic->getName()));

        /* @phpstan-ignore while.alwaysTrue */
        while (true) {
            $message = $consumer->receive(200);

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

                if (null !== $onMessageCallable) {
                    $onMessageCallable($topic, $rosselMessage->getType());
                }
            } else {
                if (null !== $onIdleCallable) {
                    $onIdleCallable($topic);
                }
            }
        }
    }

    private function processRosselMessage(
        Message $message,
        Topic $topic,
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
                $topic->getName(),
            ),
            [
                'id' => $messageId,
                'topic' => $topic->getName(),
                'type' => $message->getType()->name,
            ]
        );
    }

    /**
     * @throws InvalidMessageException
     * @throws InvalidDestinationException
     * @throws Exception
     * @throws \JsonException
     */
    private function tryConsumer(
        ConsumerInterface $consumer,
        Message $message,
        Topic $topic,
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

            $this->sendLogMessage($message, $topic, true);

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

        $this->sendLogMessage($message, $topic, false);

        return false;
    }

    /**
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws Exception
     * @throws \JsonException
     */
    private function sendLogMessage(
        Message $originalMessage,
        Topic $topic,
        bool $success,
    ): void {
        $originalHeaders = $originalMessage->getRdKafkaMessage()->getHeaders();

        $areaValue = $originalHeaders[MessageHeaders::KEY_AREA];
        $area = Area::from(\is_string($areaValue) ? $areaValue : '');

        $trackId = null;

        if (\array_key_exists(MessageHeaders::KEY_TRACK_ID, $originalHeaders)
            && \is_string($originalHeaders[MessageHeaders::KEY_TRACK_ID])
        ) {
            $trackId = $originalHeaders[MessageHeaders::KEY_TRACK_ID];
        }

        $message = new Message(
            new MessageHeaders(
                area: $area,
                from: $this->appName,
                messageType: MessageType::LOG,
                trackId: $trackId,
            ),
            $success ? 'EXEC_SUCCESS' : 'EXEC_ERROR',
        );

        $this->kafkaConnector->send($topic, $message);
    }
}
