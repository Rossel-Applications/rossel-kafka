<?php

namespace Rossel\RosselKafkaPhpKit\Message;

use Rossel\RosselKafkaPhpKit\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafkaPhpKit\Model\MessageHeaders;

/**
 * Interface to be implemented by classes representing Kafka messages.
 */
interface MessageInterface
{
    /**
     * Returns the message headers.
     *
     * @return MessageHeaders The headers associated with the message.
     */
    public function getHeaders(): MessageHeaders;

    public function getJsonHeaders(): string;

    /**
     * Returns the message body.
     *
     * @return array<string, mixed> An associative array containing the message body.
     */
    public function getBody(): array;

    public function getJsonBody(): string;

    /**
     * Returns the type of the message.
     *
     * @return MessageType The type of the message.
     */
    public function getType(): MessageType;
}
