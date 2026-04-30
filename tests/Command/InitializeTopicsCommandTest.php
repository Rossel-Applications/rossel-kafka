<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Command\InitializeTopicsCommand;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Service\KafkaTopicsFetcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InitializeTopicsCommandTest extends TestCase
{
    /** @var MockObject&KafkaTopicsFetcherInterface */
    private MockObject $fetcher;

    private InitializeTopicsCommand $command;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->fetcher = $this->createMock(KafkaTopicsFetcherInterface::class);
        $this->command = new InitializeTopicsCommand($this->fetcher);
        $this->tester = new CommandTester($this->command);
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    #[Test]
    public function commandNameIsCorrect(): void
    {
        self::assertSame('rossel:kafka:initialize-topics', $this->command->getName());
    }

    #[Test]
    public function commandDescriptionIsSet(): void
    {
        self::assertNotEmpty($this->command->getDescription());
    }

    // -------------------------------------------------------------------------
    // execute() — no topics
    // -------------------------------------------------------------------------

    #[Test]
    public function executeSucceedsWhenNoTopicsConfigured(): void
    {
        $this->fetcher->method('getAll')->willReturn([]);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    // -------------------------------------------------------------------------
    // execute() — with topics (processes spawn but exit immediately when script missing)
    // -------------------------------------------------------------------------

    #[Test]
    public function executeSucceedsWithTopicsConfigured(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output.topic',
            messageTypes: [MessageType::EXEC_SUCCESS],
        );

        $this->fetcher->method('getAll')->willReturn([
            TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => $topic,
        ]);

        // kafka-topics.sh won't exist, but the process will just fail quickly and be removed
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function executeSucceedsWithMultipleTopics(): void
    {
        $topics = [];
        foreach ([
            TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE,
        ] as $configKey) {
            $topics[$configKey->name] = new Topic(
                configKey: $configKey,
                name: $configKey->value,
                messageTypes: [],
            );
        }

        $this->fetcher->method('getAll')->willReturn($topics);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }
}
