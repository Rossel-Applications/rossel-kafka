# Upgrade from 0.4 to 0.5

## Kafka record key on `Message`

`Message` now carries the Kafka record key, both when producing and when consuming.

Records with the same key always go to the same partition, so Kafka keeps their order. Without a key, records are spread across partitions and their relative order is not guaranteed.

### Producing a message with a key

Pass the key as the third constructor argument. It is optional and defaults to `null`.

Before:

```php
$message = new Message($headers, $payload);
$message->getRdKafkaMessage()->setKey($key);

$kafkaConnector->send($topic, $message);
```

After:

```php
$message = new Message($headers, $payload, $key);

$kafkaConnector->send($topic, $message);
```

The old way still sends the key, but `$message->getKey()` then returns `null`. Use the constructor argument so that both values match.

### Reading the key of a consumed message

`Message::getKey()` returns the key of the consumed record, or `null` if the record has no key.

```php
public function __invoke(Message $message): void
{
    $key = $message->getKey();
}
```

In 0.4, the consumed key was lost: `$message->getRdKafkaMessage()->getKey()` always returned `null`.

## Backward compatibility breaks

### `MessageInterface::getKey()` added

`MessageInterface` now declares:

```php
public function getKey(): ?string;
```

If your code implements `MessageInterface`, add this method.

If your code extends `Message` and already defines a `getKey()` method, make sure its signature is compatible with `getKey(): ?string`.

### Consumed messages keep their key when sent again

If your code sends a consumed `Message` object again (forwarding, replay, retry), the new record now has the same key as the original one. It goes to the partition of that key.

In 0.4, the new record had no key and could go to any partition.
