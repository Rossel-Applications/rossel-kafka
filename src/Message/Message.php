<?php

namespace Rossel\RosselKafkaPhpKit\Message;

use Rossel\RosselKafkaPhpKit\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafkaPhpKit\Model\MessageHeaders;

/**
 * Representation of a Kafka message.
 */
final readonly class Message implements MessageInterface
{
    /**
     * Initializes the message with the provided headers and body.
     *
     * @param MessageHeaders $headers The message headers.
     * @param array<string, mixed> $body The message body.
     */
    public function __construct(
        private MessageHeaders $headers,
        private array $body,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): MessageHeaders
    {
        return $this->headers;
    }

    /**
     * @throws \JsonException
     */
    public function getJsonHeaders(): string
    {
        return $this->headers->toJson();
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @throws \JsonException
     */
    public function getJsonBody(): string
    {
        return \json_encode($this->body, JSON_THROW_ON_ERROR);
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): MessageType {
        return $this->headers->getMessageType();
    }
}
