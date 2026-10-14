# Upgrade from 0.6 to 0.7

## Backward compatibility breaks

### The enqueue bundle is no longer installed

rossel-kafka no longer requires `enqueue/enqueue-bundle` or `enqueue/fs`. It never used them: it connects to Kafka through `enqueue/rdkafka` directly, which is still required.

It also no longer pre-configures the enqueue `default` transport with the broker URL.

#### If your app does not use enqueue itself

Remove the bundle from `config/bundles.php`:

```diff
-    Enqueue\Bundle\EnqueueBundle::class => ['all' => true],
```

Symfony Flex usually does it for you during `composer update`. If the line is still there, the app fails to boot with `Class "Enqueue\Bundle\EnqueueBundle" not found`.

Also remove the `enqueue/enqueue-bundle` and `enqueue/fs` entries from `symfony.lock` if Flex left them.

#### If your app uses enqueue itself

Require the packages in your own `composer.json`:

```shell
composer require enqueue/enqueue-bundle enqueue/fs
```

Then configure the transport that rossel-kafka used to set for you, in `config/packages/enqueue.yaml`:

```yaml
enqueue:
    default:
        transport: '%env(ROSSEL_KAFKA_BROKER_URL)%'
        client: ~
```

### Removed types

- `Rossel\RosselKafka\Model\MessageHeadersInterface` is removed. Type against `Rossel\RosselKafka\Model\MessageHeaders`.
- `Rossel\RosselKafka\Model\RdKafkaMessageWrapperInterface` is removed. `getRdKafkaMessage()` is now declared on `Rossel\RosselKafka\Model\MessageInterface`.
- `Rossel\RosselKafka\Exception\UnsupportedTopicException` is removed. The library never threw it.

### `MessageFactory` no longer takes a logger

`new MessageFactory($logger)` becomes `new MessageFactory()`. Nothing changes if the factory is autowired.

### Consumed message bodies are kept verbatim

`Message::getBody()` on a consumed message now returns the Kafka record value as it was received. Before, JSON bodies were decoded and re-encoded, so `{}` came back as `[]`, whitespace was stripped and a JSON string `"x"` came back as `x`.

### The bundle no longer ships an extension class

`RosselKafkaBundle` now configures itself (Symfony `AbstractBundle`). These classes and files are removed:

- `Rossel\RosselKafka\DependencyInjection\RosselKafkaExtension`
- `Rossel\RosselKafka\DependencyInjection\Configuration`
- `src/Resources/config/services.yaml`

The `rossel_kafka` config keys, the `rossel_kafka.*` container parameters and the `rossel_kafka.consumer` tag are unchanged. Nothing changes unless your code used these classes directly. The services are now wired with PHP attributes, so the bundle no longer needs `symfony/yaml`.

### `MessageType::from()` throws a `\ValueError`

An unknown message type name used to throw `Symfony\Component\HttpFoundation\Exception\BadRequestException`. It now throws a `\ValueError`, like the native `from()` of a backed enum. Update any `catch (BadRequestException)` around it.

### One log message per consumed message

`ConsumptionOrchestrator` used to send an `EXEC_ERROR` log message for each consumer that did not support the message, even when another consumer then handled it. It now sends exactly one log message per message: `EXEC_SUCCESS` when a consumer handled it, `EXEC_ERROR` otherwise.

### A failing message no longer stops the listener

An exception thrown by a consumer, or by `MessageFactory` on invalid headers, used to stop `rossel:kafka:listen`. The message is now logged as an error and acknowledged, then the listener moves on to the next one. When the message could be built, an `EXEC_ERROR` log message is sent. If you relied on the crash to retry a message, handle the retry in your consumer.

## New features

### Symfony 6.4 support

The bundle now installs on Symfony 6.4 (`symfony/process` was restricted to `^7.4`). The composer package type is now `symfony-bundle`, so Flex registers the bundle in `config/bundles.php`.

### The connection is configured on first use

`KafkaConnector` used to resolve the SSL certificates (and download the CA certificate) as soon as it was built. It now does it on the first `send()` or `createConsumer()`. A wrong certificate path or URL now fails there instead of when the service is created. No action required.

### The producer is created on first send

`KafkaConnector` no longer creates an rdkafka producer when it is built. The producer is created on the first `send()` to a producible topic, so an app that only consumes never opens one. No action required.

### Log messages can be disabled

After each consumed message, `ConsumptionOrchestrator` sends an `EXEC_SUCCESS` or `EXEC_ERROR` log message to the topic. The new `producer.send_log_messages` key turns this off. It defaults to `true`, so nothing changes unless you set it:

```yaml
rossel_kafka:
    producer:
        app_name: '%env(ROSSEL_KAFKA_PRODUCER_APP_NAME)%'
        send_log_messages: false
```

## Bug fixes

### SASL now defaults to `PLAIN`

When `sasl_mechanism` came from an env var that was not set, no mechanism was passed to librdkafka, which then used `GSSAPI`. The connector now falls back to `PLAIN`, as documented.

### `broker` and `producer` are required

A `rossel_kafka` config without the `broker` or `producer` section used to pass validation, then failed with an "Undefined array key" error while building the container. It now fails validation with a message naming the missing section. A config that worked before still works.
