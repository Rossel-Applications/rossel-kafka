<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Enqueue\RdKafka\RdKafkaMessage;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

/**
 * Representation of a Kafka message, wrapping RdKafkaMessage class.
 */
readonly class Message implements MessageInterface
{
    private RdKafkaMessage $rdKafkaMessage;

    private string $body;

    /**
     * Initializes the message with the provided headers and body.
     *
     * @param MessageHeadersInterface     $headers the message headers
     * @param array<string, mixed>|string $body    the message body
     *
     * @throws \JsonException
     */
    public function __construct(
        private MessageHeadersInterface $headers,
        array|string $body,
    ) {
        if (\is_array($body)) {
            $body = json_encode($body, \JSON_THROW_ON_ERROR);
        }

        $this->body = $body;

        $this->rdKafkaMessage = new RdKafkaMessage(
            body: $this->body,
            headers: $this->headers->toArray(),
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

    public function getBody(): string
    {
        return $this->body;
    }
}
