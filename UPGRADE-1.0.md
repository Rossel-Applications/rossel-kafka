# Upgrade from 0.7 to 1.0

## Backward compatibility breaks

### enqueue is no longer required

rossel-kafka now talks to Kafka through the `rdkafka` PHP extension directly. `enqueue/rdkafka` and `queue-interop/queue-interop` are no longer installed. The extension must be version 6 (`ext-rdkafka: ^6.0`).

If your app uses enqueue itself, require it in your own `composer.json`:

```shell
composer require enqueue/rdkafka
```

### `MessageInterface::getRdKafkaMessage()` is removed

Read the body, key and headers from the message itself:

```diff
-$message->getRdKafkaMessage()->getBody();
-$message->getRdKafkaMessage()->getKey();
-$message->getRdKafkaMessage()->getHeaders();
+$message->getBody();
+$message->getKey();
+$message->getHeaders();
```

`getBody()` and `getHeaders()` are now declared on `MessageInterface`, so a class implementing it must add them.

### `KafkaConnectorInterface` uses the rdkafka extension types

- `send()` only accepts a `Rossel\RosselKafka\Model\Topic`. Passing an `Enqueue\RdKafka\RdKafkaTopic` is no longer possible.
- `createConsumer()` returns an `RdKafka\KafkaConsumer` already subscribed to the topic, instead of an `Enqueue\RdKafka\RdKafkaConsumer`. Use `consume()` and `commit()` instead of `receive()` and `acknowledge()`.

### `MessageFactoryInterface::createMessageFromRdKafka()` takes an `RdKafka\Message`

Its parameter is now the `RdKafka\Message` returned by `KafkaConsumer::consume()`, instead of an `Enqueue\RdKafka\RdKafkaMessage`.

### `RawKafkaSerializer` is removed

`Rossel\RosselKafka\Service\Serializer\RawKafkaSerializer` only adapted enqueue's serializer. The record value is still the raw body and metadata still travels in Kafka headers, so nothing changes on the wire.

### `Interop\Queue` exceptions are no longer thrown

`KafkaConnector::send()` no longer declares `Interop\Queue\Exception` and its subclasses. Remove any `catch` for them.

### `send()` waits for the broker and throws when the message is not delivered

`send()` used to queue the message and return at once. Messages were only flushed when the process exited, after the caller had already acted on a send it took as done, and a delivery failure was never reported. A crash or an unreachable broker lost the message silently.

`send()` now returns once the broker has acknowledged the message, and throws `Rossel\RosselKafka\Exception\MessageNotDeliveredException` when the broker refuses it or does not acknowledge it within 30 seconds (`delivery.timeout.ms`, which used to be 6 days). Catch it where a failed send must not stop the process, and do not acknowledge the source of the message:

```php
try {
    $kafkaConnector->send($topic, $message);
    $event->markAsPublished();
} catch (MessageNotDeliveredException $e) {
    $event->markAsFailed($e->getMessage()); // sent again on the next run
}
```

## New features

### Consumed messages are logged with their position

The `id` in the consumer logs used to be empty. It is now `partition:offset`, for example `0:42`.

### `onIdleCallable` is called while waiting

`ConsumptionOrchestrator::listen()` used to block until a message arrived, so `onIdleCallable` was never called. It is now called every 500 ms when no message is available. The "Consumer is now listening" log is written once, when the consumer is created, instead of before each message.

### An unexpected Kafka error stops the listener with its code

A Kafka error other than "no message yet" now throws a `\RuntimeException` with the librdkafka error code, instead of a `\LogicException`. `rossel:kafka:listen` logs it and exits, as before.
