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
