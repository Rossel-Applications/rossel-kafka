<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Enqueue\RdKafka\RdKafkaMessage;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

/**
 * Representation of a Kafka message, wrapping RdKafkaMessage class.
 */
final readonly class Message implements MessageInterface
{
    private RdKafkaMessage $rdKafkaMessage;

    /**
     * Initializes the message with the provided headers and body.
     *
     * @param MessageHeadersInterface $headers the message headers
     * @param array<string, mixed>    $body    the message body
     *
     * @throws \JsonException
     */
    public function __construct(
        private MessageHeadersInterface $headers,
        private array $body,
    ) {
        $this->rdKafkaMessage = new RdKafkaMessage(
            $this->getJsonBody(),
            $this->headers->toArray(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): MessageType
    {
        return $this->headers->getMessageType();
    }

    public function getRdKafkaMessage(): RdKafkaMessage
    {
        return $this->rdKafkaMessage;
    }

    /**
     * @throws \JsonException
     */
    private function getJsonBody(): string
    {
        return json_encode($this->body, \JSON_THROW_ON_ERROR);
    }
}
