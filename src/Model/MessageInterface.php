<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

/**
 * Interface to be implemented by classes representing Kafka messages.
 */
interface MessageInterface
{
    /**
     * Returns the Kafka record value.
     */
    public function getBody(): string;

    /**
     * Returns the Kafka record headers.
     *
     * @return array<string, scalar>
     */
    public function getHeaders(): array;

    /**
     * Returns the type of the message.
     *
     * @return MessageType the type of the message
     */
    public function getType(): MessageType;

    /**
     * Returns the Kafka record key.
     *
     * @return string|null the record key, or null if the record has no key
     */
    public function getKey(): ?string;
}
