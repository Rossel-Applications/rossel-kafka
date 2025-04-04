# Rossel Kafka

A ready-to-use PHP library for seamless communication with Rossel's Kafka infrastructure, handling both production and consumption of messages.

## Installation

```shell
composer require rossel/rossel-kafka
```

## Configuration

### Bundle configuration

```yaml
rossel_kafka:
  broker_url: %env(KAFKA_BROKER_URL)%
```

### Broker configuration (docker)

// WIP

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
    brokerUrl: 'localhost:9092',
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
