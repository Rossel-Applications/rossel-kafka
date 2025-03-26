<?php

namespace Rossel\RosselKafkaPhpKit\Service\Dispatcher;

use Enqueue\RdKafka\RdKafkaContext;
use Psr\Log\LoggerInterface;
use Rossel\RosselKafkaPhpKit\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafkaPhpKit\Exception\UnsupportedTopicException;
use Rossel\RosselKafkaPhpKit\Message\MessageInterface;

final class MessageDispatcher implements MessageDispatcherInterface
{
    public function __construct(
        private string $address,
        private int $port,
        private RdKafkaContext $rdKafkaContext,
        private LoggerInterface $logger,
    )
    {
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(MessageInterface $message, array $topics): void
    {
        $this->checkTopicSupported($message, $topics);

        $this->rdKafkaContext->createMessage(
            body: $message->getJsonBody(),
        );
    }

    /**
     * Dispatches a message to the specified Kafka topics.
     *
     * @param MessageInterface $message The message to be dispatched.
     * @param array<array-key, KafkaTopic> $topics An array of topic where the message will be sent.
     */
    private function checkTopicSupported(MessageInterface $message, array $topics): void
    {
        foreach ($topics as $topic) {
            if (!in_array(
                $topic,
                ($messageType = $message->getType())->getTopics())
            ) {
                throw new UnsupportedTopicException($topic, $messageType);
            }
        }
    }
}
