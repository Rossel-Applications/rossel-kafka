# Rossel Kafka

A ready-to-use PHP library for seamless communication with Rossel's Kafka infrastructure, handling both production and consumption of messages.

## Installation

```shell
composer require rossel/rossel-kafka
```

## Configuration

```yaml
rossel_kafka:
    broker:
        url: '%env(ROSSEL_KAFKA_BROKER_URL)%'
        topics:
            account_api_public_log_output_v1_json_delete: '%env(ACCOUNT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            authentication_api_public_log_output_v1_json_delete: '%env(AUTHENTICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            public_dead_letter_inout_v1_json_delete_d30: '%env(PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30)%'
            erp_subscription_api_public_log_output_v1_json_delete: '%env(ERP_SUBSCRIPTION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            public_inheritance_output_v1_json_delete: '%env(PUBLIC_INHERITANCE_OUTPUT_V1_JSON_DELETE)%'
            inheritance_api_public_log_output_v1_json_delete: '%env(INHERITANCE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            notification_api_public_log_output_v1_json_delete: '%env(NOTIFICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            public_notification_input_v1_json_delete: '%env(PUBLIC_NOTIFICATION_INPUT_V1_JSON_DELETE)%'
            offer_api_public_log_output_v1_json_delete: '%env(OFFER_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            public_offer_output_v1_json_delete: '%env(PUBLIC_OFFER_OUTPUT_V1_JSON_DELETE)%'
            profile_api_public_log_output_v1_json_delete: '%env(PROFILE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            purchase_api_public_log_output_v1_json_delete: '%env(PURCHASE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            public_log_output_v1_json_delete: '%env(PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            public_offer_input_v1_json_delete: '%env(PUBLIC_OFFER_INPUT_V1_JSON_DELETE)%'
            public_subscription_input_v1_json_delete: '%env(PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE)%'
            public_subscription_output_v1_json_delete: '%env(PUBLIC_SUBSCRIPTION_OUTPUT_V1_JSON_DELETE)%'
            public_contact_input_v1_json_delete: '%env(PUBLIC_CONTACT_INPUT_V1_JSON_DELETE)%'
            input_api_public_log_output_v1_json_delete: '%env(INPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            output_api_public_log_output_v1_json_delete: '%env(OUTPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)%'
            public_profile_output_v1_json_delete: '%env(PUBLIC_PROFILE_OUTPUT_V1_JSON_DELETE)%'
    producer:
        app_name: '%env(ROSSEL_KAFKA_PRODUCER_APP_NAME)%'
```

Each topic key maps to the actual Kafka topic name provided via environment variable.

## Broker authentication

Authentication is **optional** — typically required in production, but not in local development.

All authentication options live under `broker.authentication` in the bundle config (already wired to env vars by the bundle's default config file).

### SASL

Set both username and password to enable SASL:

```dotenv
ROSSEL_KAFKA_BROKER_SASL_USERNAME=my-user
ROSSEL_KAFKA_BROKER_SASL_PASSWORD=my-password
# Mechanism: PLAIN (default), SCRAM-SHA-256 or SCRAM-SHA-512
ROSSEL_KAFKA_BROKER_SASL_MECHANISM=PLAIN
```

### SSL — CA certificate

Two options, mutually exclusive — the local file takes priority:

**From a local file:**
```dotenv
ROSSEL_KAFKA_BROKER_SSL_CA_CERTIFICATE_PATH=/path/to/ca.pem
```

**From a URL (downloaded automatically, cached in `/tmp`):**
```dotenv
ROSSEL_KAFKA_BROKER_SSL_CA_CERTIFICATE_URL=https://...
```

### mTLS — client certificate

For mutual TLS, provide the client certificate and private key in addition to the CA certificate above.
Each variable accepts either a **PEM file path** or **raw PEM content**:

```dotenv
ROSSEL_KAFKA_BROKER_SSL_CLIENT_CERTIFICATE=/path/to/client.crt.pem
ROSSEL_KAFKA_BROKER_SSL_CLIENT_KEY=/path/to/client.key.pem
# Optional: password protecting the private key
ROSSEL_KAFKA_BROKER_SSL_CLIENT_KEY_PASSWORD=secret
```

### Security protocol — auto-selected

The bundle selects the `security.protocol` rdkafka option automatically based on which credentials are provided:

| CA cert / client cert | SASL | Protocol         |
|-----------------------|------|------------------|
| no                    | no   | `plaintext`      |
| yes                   | no   | `ssl`            |
| no                    | yes  | `sasl_plaintext` |
| yes                   | yes  | `sasl_ssl`       |

> Unset or empty variables are treated as `null` — no configuration needed for unauthenticated brokers.

## Usage

### Send a message to a topic

```php
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;
use Rossel\RosselKafka\Service\KafkaTopicsFetcher;

// Via Symfony DI (recommended)
$topic = $kafkaTopicsFetcher->get(TopicConfigKeys::PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE);

$message = new Message(
    headers: new MessageHeaders(
        area: Area::FRANCE,
        from: 'my-app',
        messageType: MessageType::CANCEL_B2C_SUBSCRIPTION,
    ),
    body: ['foo' => 'bar'],
);

$kafkaConnector->send($topic, $message);
```

### Consume messages

Implement `ConsumerInterface` and tag your service with `rossel_kafka.consumer`:

```php
use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\Topic;

final class MyConsumer implements ConsumerInterface
{
    public function supportsTopic(Topic $topic): bool
    {
        return TopicConfigKeys::PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE === $topic->getConfigKey();
    }

    public function supportsMessageType(Message $message): bool
    {
        return MessageType::CANCEL_B2C_SUBSCRIPTION === $message->getType();
    }

    public function __invoke(Message $message): void
    {
        // handle message
    }
}
```

### Listen to topics

```shell
# Listen to all topics (one process per topic)
php bin/console rossel:kafka:listen

# Listen to specific topics (comma-separated topic values)
php bin/console rossel:kafka:listen --topics=public_subscription_input_v1_json_delete,public_offer_input_v1_json_delete
```

### Fetch topic metadata

```php
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

// Get a single topic by config key
$topic = $kafkaTopicsFetcher->get(TopicConfigKeys::PUBLIC_OFFER_OUTPUT_V1_JSON_DELETE);

// Get all topics
$topics = $kafkaTopicsFetcher->getAll();

// Get topics supporting a given message type
$topics = $kafkaTopicsFetcher->getByMessageType(MessageType::SYNC_B2C_ERP_OFFERS);
```
