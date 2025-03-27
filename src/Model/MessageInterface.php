<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Model;

use Enqueue\RdKafka\RdKafkaMessage;
use Rossel\RosselKafkaPhpKit\Enum\MessageHeaders\MessageType;

/**
 * Interface to be implemented by classes representing Kafka messages.
 */
interface MessageInterface
{
    /**
     * Returns the message headers.
     *
     * @return MessageHeadersInterface the headers associated with the message
     */
    public function getHeaders(): MessageHeadersInterface;

    /**
     * Returns the message body.
     *
     * @return array<string, mixed> an associative array containing the message body
     */
    public function getBody(): array;

    public function getJsonBody(): string;

    /**
     * Returns the type of the message.
     *
     * @return MessageType the type of the message
     */
    public function getType(): MessageType;

    public function getRdKafkaMessage(): RdKafkaMessage;
}
