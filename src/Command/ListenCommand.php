<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Command;

use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Command\Output\PlainListenOutputStrategy;
use Rossel\RosselKafka\Command\Output\TableListenOutputStrategy;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Orchestrator\ConsumptionOrchestratorInterface;
use Rossel\RosselKafka\Service\KafkaTopicsFetcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class ListenCommand extends Command
{
    private const COMMAND_NAME = 'rossel:kafka:listen';
    private const COMMAND_DESCRIPTION = 'Launch Kafka listeners';

    private const COMMAND_OPTION_TOPIC_NAME = 'topics';
    private const COMMAND_OPTION_TOPIC_SHORTCUT = 't';
    private const COMMAND_OPTION_TOPIC_DESCRIPTION = 'Topics to listen to (separated by comma)';

    public function __construct(
        private readonly ConsumptionOrchestratorInterface $consumptionOrchestrator,
        private readonly KafkaTopicsFetcherInterface $kafkaTopicsFetcher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            name: self::COMMAND_NAME,
        );
    }

    protected function configure(): void
    {
        $this->setDescription(self::COMMAND_DESCRIPTION);
        $this->addOption(
            name: self::COMMAND_OPTION_TOPIC_NAME,
            shortcut: self::COMMAND_OPTION_TOPIC_SHORTCUT,
            mode: InputOption::VALUE_OPTIONAL,
            description: self::COMMAND_OPTION_TOPIC_DESCRIPTION,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Child processes spawned for multi-topic use --no-interaction; they output
        // plain parseable text. The parent (and single-topic mode) uses the table
        // strategy which streams each message as a formatted row.
        $outputStrategy = $input->isInteractive()
            ? new TableListenOutputStrategy($output)
            : new PlainListenOutputStrategy($output);

        /** @var string $topicsInput */
        $topicsInput = $input->getOption('topics');

        $topics = $this->extractConsumableTopicsFromString($topicsInput);

        if (0 === ($topicsCount = \count($topics))) {
            $message = 'This topic is not configured for consumption.';
            $outputStrategy->warning($message);
            $this->logger->warning($message);

            return Command::SUCCESS;
        }

        if (1 === $topicsCount) {
            try {
                $this->consumptionOrchestrator->listen(
                    topic: $topics[0],
                    onStartCallable: $outputStrategy->onStart(...),
                    onIdleCallable: $outputStrategy->onIdle(...),
                    onMessageCallable: $outputStrategy->onMessage(...),
                );

                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        // Multi-topic: spawn one child process per topic and stream their output
        // as table rows, each topic identified by its own color column.
        $tableStrategy = new TableListenOutputStrategy($output);

        return $this->runParallel($topics, $tableStrategy);
    }

    /**
     * Spawns one child process per topic and formats their output as table rows.
     *
     * @param list<Topic> $topics
     */
    private function runParallel(array $topics, TableListenOutputStrategy $strategy): int
    {
        $baseArgs = ['php', 'bin/console', self::COMMAND_NAME, '--no-interaction'];

        /** @var list<array{topic: Topic, process: Process}> $running */
        $running = [];

        foreach ($topics as $topic) {
            $strategy->onStart($topic); // pre-register color before output starts

            $process = new Process([
                ...$baseArgs,
                \sprintf('--%s=%s', self::COMMAND_OPTION_TOPIC_NAME, $topic->getConfigKey()->value),
            ]);

            $process->start();

            $running[] = ['topic' => $topic, 'process' => $process];
        }

        do {
            foreach ($running as $key => $item) {
                $this->routeProcessOutput($item['process'], $item['topic'], $strategy);

                if (!$item['process']->isRunning()) {
                    unset($running[$key]);
                }
            }

            usleep(50000); // 50 ms polling
        } while (!empty($running));

        return Command::SUCCESS;
    }

    /**
     * Reads incremental output from a child process and prints matching lines as table rows.
     *
     * Expected plain output from children (PlainListenOutputStrategy):
     *   "Starting listening on {topic}..."  → skip (topic is already the column header)
     *   "Event received: {TYPE}"            → print row with TYPE as message
     *   any other line                      → print row as-is
     */
    private function routeProcessOutput(Process $process, Topic $topic, TableListenOutputStrategy $strategy): void
    {
        foreach ([$process->getIncrementalOutput(), $process->getIncrementalErrorOutput()] as $chunk) {
            if ('' === $chunk) {
                continue;
            }

            foreach (explode("\n", rtrim($chunk, "\n")) as $line) {
                if ('' === $line || str_starts_with($line, 'Starting listening on ')) {
                    continue;
                }

                $message = str_starts_with($line, 'Event received: ')
                    ? substr($line, \strlen('Event received: '))
                    : $line;

                $strategy->printRow(date('H:i:s'), $topic->getName(), $message);
            }
        }
    }

    /**
     * @return list<Topic>
     */
    private function extractConsumableTopicsFromString(?string $topics): array
    {
        if (null === $topics || '' === str_replace([',', ' '], '', $topics)) {
            return array_values($this->kafkaTopicsFetcher->getAll());
        }

        $results = [];

        foreach (explode(',', $topics) as $topicString) {
            $resolvedTopic = $this->kafkaTopicsFetcher->get(TopicConfigKeys::from(trim($topicString)));

            if ($resolvedTopic->isConsumable()) {
                $results[] = $resolvedTopic;
            }
        }

        return $results;
    }
}
