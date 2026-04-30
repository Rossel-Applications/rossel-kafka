<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Enum\Topic\TopicDirection;
use Rossel\RosselKafka\Model\Topic;

final class TopicTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getters
    // -------------------------------------------------------------------------

    #[Test]
    public function gettersReturnConstructorValues(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'my.kafka.topic',
            messageTypes: [MessageType::EXEC_SUCCESS],
        );

        self::assertSame(TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE, $topic->getConfigKey());
        self::assertSame('my.kafka.topic', $topic->getName());
        self::assertSame([MessageType::EXEC_SUCCESS], $topic->getMessageTypes());
    }

    #[Test]
    public function messageTypesDefaultsToEmptyArray(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'topic.name',
        );

        self::assertSame([], $topic->getMessageTypes());
    }

    // -------------------------------------------------------------------------
    // direction
    // -------------------------------------------------------------------------

    #[Test]
    public function outputTopicHasConsumeDirection(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output',
        );

        self::assertSame(TopicDirection::CONSUME, $topic->getDirection());
        self::assertTrue($topic->isConsumable());
        self::assertFalse($topic->isProducible());
    }

    #[Test]
    public function inputTopicHasProduceDirection(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE,
            name: 'subscription.input',
        );

        self::assertSame(TopicDirection::PRODUCE, $topic->getDirection());
        self::assertFalse($topic->isConsumable());
        self::assertTrue($topic->isProducible());
    }

    #[Test]
    public function inoutTopicHasBothDirection(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30,
            name: 'dead-letter',
        );

        self::assertSame(TopicDirection::BOTH, $topic->getDirection());
        self::assertTrue($topic->isConsumable());
        self::assertTrue($topic->isProducible());
    }

    #[Test]
    public function inputApiOutputTopicHasConsumeDirection(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_INPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'input-api.log.output',
        );

        self::assertSame(TopicDirection::CONSUME, $topic->getDirection());
        self::assertTrue($topic->isConsumable());
        self::assertFalse($topic->isProducible());
    }

    /**
     * @return iterable<string, array{TopicConfigKeys, TopicDirection}>
     */
    public static function directionProvider(): iterable
    {
        yield 'OUTPUT topic → CONSUME' => [
            TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_OUTPUT_V1_JSON_DELETE,
            TopicDirection::CONSUME,
        ];
        yield 'INPUT topic → PRODUCE' => [
            TopicConfigKeys::KAFKA_TOPIC_PUBLIC_OFFER_INPUT_V1_JSON_DELETE,
            TopicDirection::PRODUCE,
        ];
        yield 'INPUT topic (contact) → PRODUCE' => [
            TopicConfigKeys::KAFKA_TOPIC_PUBLIC_CONTACT_INPUT_V1_JSON_DELETE,
            TopicDirection::PRODUCE,
        ];
        yield 'INOUT topic → BOTH' => [
            TopicConfigKeys::KAFKA_TOPIC_PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30,
            TopicDirection::BOTH,
        ];
        yield 'OUTPUT topic (account log) → CONSUME' => [
            TopicConfigKeys::KAFKA_TOPIC_ACCOUNT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            TopicDirection::CONSUME,
        ];
        yield 'INPUT_API log OUTPUT topic → CONSUME' => [
            TopicConfigKeys::KAFKA_TOPIC_INPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            TopicDirection::CONSUME,
        ];
    }

    #[Test]
    #[DataProvider('directionProvider')]
    public function directionIsCorrectlyDerivedFromConfigKey(TopicConfigKeys $configKey, TopicDirection $expected): void
    {
        $topic = new Topic(configKey: $configKey, name: 'topic');

        self::assertSame($expected, $topic->getDirection());
    }

    // -------------------------------------------------------------------------
    // supportsMessageType
    // -------------------------------------------------------------------------

    #[Test]
    public function supportsMessageTypeReturnsTrueForIncludedType(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'topic',
            messageTypes: [MessageType::EXEC_SUCCESS, MessageType::EXEC_ERROR],
        );

        self::assertTrue($topic->supportsMessageType(MessageType::EXEC_SUCCESS));
        self::assertTrue($topic->supportsMessageType(MessageType::EXEC_ERROR));
    }

    #[Test]
    public function supportsMessageTypeReturnsFalseForExcludedType(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'topic',
            messageTypes: [MessageType::EXEC_SUCCESS],
        );

        self::assertFalse($topic->supportsMessageType(MessageType::LOG));
        self::assertFalse($topic->supportsMessageType(MessageType::EXEC_ERROR));
    }

    #[Test]
    public function supportsMessageTypeReturnsFalseForEmptyTypes(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30,
            name: 'dead-letter',
            messageTypes: [],
        );

        self::assertFalse($topic->supportsMessageType(MessageType::LOG));
        self::assertFalse($topic->supportsMessageType(MessageType::EXEC_SUCCESS));
    }

    /**
     * @return iterable<string, array{MessageType, bool}>
     */
    public static function messageTypeSupportProvider(): iterable
    {
        yield 'EXEC_SUCCESS is supported' => [MessageType::EXEC_SUCCESS, true];
        yield 'EXEC_ERROR is supported' => [MessageType::EXEC_ERROR, true];
        yield 'LOG is not supported' => [MessageType::LOG, false];
        yield 'CANCEL_B2C_SUBSCRIPTION is not supported' => [MessageType::CANCEL_B2C_SUBSCRIPTION, false];
    }

    #[Test]
    #[DataProvider('messageTypeSupportProvider')]
    public function supportsMessageTypeDataset(MessageType $type, bool $expected): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log-topic',
            messageTypes: [MessageType::EXEC_SUCCESS, MessageType::EXEC_ERROR],
        );

        self::assertSame($expected, $topic->supportsMessageType($type));
    }
}
