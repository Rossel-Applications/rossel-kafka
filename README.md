# Rossel Kafka PHP kit

A ready-to-use PHP library for seamless communication with Rossel's Kafka infrastructure, handling both production and consumption of messages.

## Installation

```shell
composer require rossel/rossel-kafka-php-kit
```

## Configuration

```yaml
rossel_kafka:
  address: %env(KAFKA_BROKER_ADDRESS)%
  port: %env(KAFKA_BROKER_PORT)%
```

## Usage

### Send a message to a topic

#### Without Symfony Dependency Injection

```php
use Rossel\RosselKafka\Service\Connector\KafkaConnector;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;

$kafkaConnector = new KafkaConnector(
    host: 'localhost',
    port: 9092,
);

$message = new Message(
    headers: new Rossel\RosselKafka\Model\MessageHeaders(
        area: Area::FRANCE,
        from: 'my-app',
        messageType: \Rossel\RosselKafka\Enum\MessageHeaders\MessageType::SYNC_B2C_ERP_SUBSCRIPTION,
    ),
    body: [
        'foo' => 'bar',
    ],
)

$kafkaConnector->send(KafkaTopic::SYNC_ERP, $message);
```
