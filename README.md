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
