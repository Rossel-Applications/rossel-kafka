<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

/**
 * Representation of a Kafka message.
 */
readonly class Message implements MessageInterface
{
    private string $body;

    /**
     * Initializes the message with the provided headers and body.
     *
     * @param MessageHeaders                 $headers the message headers
     * @param array<array-key, mixed>|string $body    the message body
     * @param string|null                    $key     the Kafka record key, used for partitioning and compaction
     *
     * @throws \JsonException
     */
    public function __construct(
        private MessageHeaders $headers,
        array|string $body,
        private ?string $key = null,
    ) {
        if (\is_array($body)) {
            $body = json_encode($body, \JSON_THROW_ON_ERROR);
        }

        $this->body = $body;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): MessageType
    {
        return $this->headers->getMessageType();
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return $this->headers->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
