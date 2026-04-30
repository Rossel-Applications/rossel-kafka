<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Exception\UnconfiguredTopicException;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\KafkaTopicsFetcher;

final class KafkaTopicsFetcherTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor / initialization
    // -------------------------------------------------------------------------

    #[Test]
    public function constructorIgnoresNullTopics(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => null,
        ]);

        self::assertEmpty($fetcher->getAll());
    }

    #[Test]
    public function constructorIgnoresEmptyStringTopics(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => '',
        ]);

        self::assertEmpty($fetcher->getAll());
    }

    #[Test]
    public function constructorInitializesConfiguredTopics(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'public.log.output',
        ]);

        self::assertCount(1, $fetcher->getAll());
    }

    #[Test]
    public function constructorSkipsNullAndKeepsValid(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'public.log.output',
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30->value => null,
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->value => 'subscriptions.input',
        ]);

        self::assertCount(2, $fetcher->getAll());
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[Test]
    public function getReturnsTopicForConfiguredKey(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'public.log.output',
        ]);

        $topic = $fetcher->get(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE);

        self::assertInstanceOf(Topic::class, $topic);
        self::assertSame('public.log.output', $topic->getName());
        self::assertSame(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE, $topic->getConfigKey());
    }

    #[Test]
    public function getThrowsUnconfiguredTopicExceptionForMissingKey(): void
    {
        $fetcher = new KafkaTopicsFetcher([]);

        $this->expectException(UnconfiguredTopicException::class);

        $fetcher->get(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE);
    }

    #[Test]
    public function getThrowsWhenTopicWasNullInConfig(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => null,
        ]);

        $this->expectException(UnconfiguredTopicException::class);

        $fetcher->get(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE);
    }

    // -------------------------------------------------------------------------
    // getAll()
    // -------------------------------------------------------------------------

    #[Test]
    public function getAllReturnsEmptyArrayWhenNoTopicsConfigured(): void
    {
        $fetcher = new KafkaTopicsFetcher([]);

        self::assertSame([], $fetcher->getAll());
    }

    #[Test]
    public function getAllReturnsAllConfiguredTopicsIndexedByEnumName(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'log.topic',
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->value => 'sub.input.topic',
        ]);

        $all = $fetcher->getAll();

        self::assertArrayHasKey(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name, $all);
        self::assertArrayHasKey(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->name, $all);
        self::assertSame('log.topic', $all[TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name]->getName());
    }

    // -------------------------------------------------------------------------
    // getByMessageType()
    // -------------------------------------------------------------------------

    #[Test]
    public function getByMessageTypeReturnsTopicsSupportingTheType(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            // This topic supports EXEC_SUCCESS and EXEC_ERROR
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'log.topic',
        ]);

        $topics = $fetcher->getByMessageType(MessageType::EXEC_SUCCESS);

        self::assertCount(1, $topics);
        self::assertSame('log.topic', $topics[0]->getName());
    }

    #[Test]
    public function getByMessageTypeReturnsEmptyArrayWhenNoMatch(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            // This topic supports EXEC_SUCCESS and EXEC_ERROR only
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'log.topic',
        ]);

        $topics = $fetcher->getByMessageType(MessageType::CANCEL_B2C_SUBSCRIPTION);

        self::assertEmpty($topics);
    }

    #[Test]
    public function getByMessageTypeReturnsMultipleMatchingTopics(): void
    {
        $fetcher = new KafkaTopicsFetcher([
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'log.topic',
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_ACCOUNT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'account.log.topic',
        ]);

        // Both topics support EXEC_SUCCESS
        $topics = $fetcher->getByMessageType(MessageType::EXEC_SUCCESS);

        self::assertCount(2, $topics);
    }

    // -------------------------------------------------------------------------
    // Topic message type mapping
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{TopicConfigKeys, MessageType}>
     */
    public static function topicMessageTypeMappingProvider(): iterable
    {
        yield 'subscription input supports CANCEL_B2C_SUBSCRIPTION' => [
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE,
            MessageType::CANCEL_B2C_SUBSCRIPTION,
        ];
        yield 'subscription output supports SYNC_B2C_ERP_SUBSCRIPTION' => [
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_OUTPUT_V1_JSON_DELETE,
            MessageType::SYNC_B2C_ERP_SUBSCRIPTION,
        ];
        yield 'notification input supports SEND_B2C_EMAIL_NOTIFICATION' => [
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_NOTIFICATION_INPUT_V1_JSON_DELETE,
            MessageType::SEND_B2C_EMAIL_NOTIFICATION,
        ];
        yield 'inheritance output supports SYNC_B2C_INHERITANCE' => [
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_INHERITANCE_OUTPUT_V1_JSON_DELETE,
            MessageType::SYNC_B2C_INHERITANCE,
        ];
        yield 'offer output supports SYNC_B2C_ERP_OFFERS' => [
            TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_OFFER_OUTPUT_V1_JSON_DELETE,
            MessageType::SYNC_B2C_ERP_OFFERS,
        ];
    }

    #[Test]
    #[DataProvider('topicMessageTypeMappingProvider')]
    public function topicSupportsExpectedMessageType(TopicConfigKeys $configKey, MessageType $expectedType): void
    {
        $fetcher = new KafkaTopicsFetcher([
            $configKey->value => 'topic.name',
        ]);

        $topic = $fetcher->get($configKey);

        self::assertTrue($topic->supportsMessageType($expectedType));
    }
}
