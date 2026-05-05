<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Serializer;

use Enqueue\RdKafka\RdKafkaMessage;
use Enqueue\RdKafka\Serializer;

/**
 * Treats the Kafka record value as the raw message body and relies on the real
 * Kafka headers (set by RdKafkaProducer::send via `producev` and read back by
 * RdKafkaConsumer::doReceive) to carry message headers.
 *
 * This is the contract used by Rossel's Kafka cluster: the value is the
 * business JSON payload as-is, and metadata travels in actual Kafka headers —
 * not wrapped in the `{body, properties, headers}` envelope expected by the
 * default JsonSerializer shipped with enqueue/rdkafka.
 */
final class RawKafkaSerializer implements Serializer
{
    public function toString(RdKafkaMessage $message): string
    {
        return $message->getBody();
    }

    public function toMessage(string $string): RdKafkaMessage
    {
        return new RdKafkaMessage($string);
    }
}
