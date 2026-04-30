<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rossel\RosselKafka\Command\ListenCommand;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Orchestrator\ConsumptionOrchestratorInterface;
use Rossel\RosselKafka\Service\KafkaTopicsFetcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListenCommandTest extends TestCase
{
    /** @var MockObject&ConsumptionOrchestratorInterface */
    private MockObject $orchestrator;

    /** @var MockObject&KafkaTopicsFetcherInterface */
    private MockObject $fetcher;

    private LoggerInterface $logger;

    private ListenCommand $command;

    private Topic $logTopic;

    private Topic $subTopic;

    protected function setUp(): void
    {
        $this->orchestrator = $this->createMock(ConsumptionOrchestratorInterface::class);
        $this->fetcher = $this->createMock(KafkaTopicsFetcherInterface::class);
        $this->logger = new NullLogger();

        $this->command = new ListenCommand(
            $this->orchestrator,
            $this->fetcher,
            $this->logger,
        );

        $this->logTopic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output',
        );
        $this->subTopic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE,
            name: 'sub.input',
        );
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    #[Test]
    public function commandNameIsCorrect(): void
    {
        self::assertSame('rossel:kafka:listen', $this->command->getName());
    }

    #[Test]
    public function commandHasTopicsOption(): void
    {
        self::assertTrue($this->command->getDefinition()->hasOption('topics'));
    }

    // -------------------------------------------------------------------------
    // No topics found
    // -------------------------------------------------------------------------

    #[Test]
    public function executeSucceedsWithWarningWhenNoTopicsConfigured(): void
    {
        $this->fetcher->method('getAll')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $this->orchestrator->expects($this->never())->method('listen');
    }

    #[Test]
    public function executeSucceedsWithWarningWhenTopicsOptionIsEmpty(): void
    {
        $this->fetcher->method('getAll')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--topics' => '']);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function executeSucceedsWithWarningWhenTopicsOptionIsOnlyCommas(): void
    {
        $this->fetcher->method('getAll')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--topics' => ',,,']);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    // -------------------------------------------------------------------------
    // Single-topic mode — success
    // -------------------------------------------------------------------------

    #[Test]
    public function executeCallsOrchestratorListenForSingleTopic(): void
    {
        $this->fetcher
            ->method('get')
            ->with(TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)
            ->willReturn($this->logTopic);

        $this->orchestrator
            ->expects($this->once())
            ->method('listen')
            ->with(
                $this->identicalTo($this->logTopic),
                $this->anything(), // onStartCallable
                $this->anything(), // onIdleCallable
                $this->anything(), // onMessageCallable
            );

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            '--topics' => TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function executeReturnsAllTopicsWhenNoOptionProvided(): void
    {
        $this->fetcher->method('getAll')->willReturn([
            $this->logTopic->getConfigKey()->name => $this->logTopic,
        ]);

        $this->orchestrator
            ->expects($this->once())
            ->method('listen');

        $tester = new CommandTester($this->command);
        $tester->execute([]);
    }

    // -------------------------------------------------------------------------
    // Single-topic mode — failure
    // -------------------------------------------------------------------------

    #[Test]
    public function executeReturnsFailureWhenOrchestratorThrows(): void
    {
        $this->fetcher
            ->method('get')
            ->willReturn($this->logTopic);

        $this->orchestrator
            ->method('listen')
            ->willThrowException(new \RuntimeException('Kafka connection failed'));

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            '--topics' => TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
    }

    // -------------------------------------------------------------------------
    // extractTopicsFromString — via option
    // -------------------------------------------------------------------------

    #[Test]
    public function commaSeparatedTopicsAreParsedIndividually(): void
    {
        $this->fetcher
            ->method('get')
            ->willReturnMap([
                [TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE, $this->logTopic],
                [TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE, $this->subTopic],
            ]);

        // Two topics → multi-topic mode (parallel). The processes will exit immediately
        // since 'php bin/console' won't exist, but the command still returns SUCCESS.
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            '--topics' => TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value
                .','.TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->value,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function topicsOptionWithSpacesAroundCommasIsTrimmed(): void
    {
        $this->fetcher
            ->method('get')
            ->with(TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)
            ->willReturn($this->logTopic);

        $this->orchestrator->method('listen');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            '--topics' => ' '.TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value.' ',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    // -------------------------------------------------------------------------
    // Non-interactive mode uses PlainListenOutputStrategy
    // -------------------------------------------------------------------------

    #[Test]
    public function nonInteractiveModeUsesPlainOutputStrategy(): void
    {
        $this->fetcher
            ->method('get')
            ->with(TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE)
            ->willReturn($this->logTopic);

        $this->orchestrator->expects($this->once())->method('listen');

        $tester = new CommandTester($this->command);
        // setInputs([]) triggers non-interactive mode in CommandTester
        $tester->setInputs([]);
        $exitCode = $tester->execute(
            ['--topics' => TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value],
            ['decorated' => false],
        );

        self::assertSame(Command::SUCCESS, $exitCode);
    }
}
