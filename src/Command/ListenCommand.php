<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Command;

use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Orchestrator\ConsumptionOrchestrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class ListenCommand extends Command
{
    private const COMMAND_NAME = 'rossel:kafka:listen';
    private const COMMAND_DESCRIPTION = 'Launch Kafka listeners';

    private const COMMAND_OPTION_TOPIC_NAME = 'topics';
    private const COMMAND_OPTION_TOPIC_SHORTCUT = 't';
    private const COMMAND_OPTION_TOPIC_DESCRIPTION = 'Topics to listen to (separated by comma)';

    private ?SymfonyStyle $io = null;

    public function __construct(
        private readonly ConsumptionOrchestrator $consumptionOrchestrator,
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
        $this->io = new SymfonyStyle($input, $output);

        $topicsInput = $input->getOption('topics');

        $topics = $this->extractTopicsFromString($topicsInput);

        if (0 === ($topicsCount = \count($topics))) {
            $message = 'No topics found.';
            $this->io->warning($message);
            $this->logger->warning($message);

            return Command::SUCCESS;
        }

        if (1 === $topicsCount) {
            try {
                $this->consumptionOrchestrator->listen(
                    topic: $topics[0],
                    onStartCallable: self::onStart(...),
                );

                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        $processes = [];

        foreach ($topics as $topic) {
            $process = new Process([
                'php',
                'bin/console',
                self::COMMAND_NAME,
                \sprintf('--%s=%s', self::COMMAND_OPTION_TOPIC_NAME, $topic->name),
            ]);

            $process->start();
            $processes[] = $process;
        }

        do {
            foreach ($processes as $key => $process) {
                if ($process->isRunning()) {
                    echo $process->getIncrementalOutput();
                    echo $process->getIncrementalErrorOutput();
                } else {
                    echo $process->getIncrementalOutput();
                    echo $process->getIncrementalErrorOutput();

                    unset($processes[$key]);
                }
            }

            usleep(100000); // 100 ms
        } while (!empty($processes));

        return Command::SUCCESS;
    }

    /**
     * @return array<array-key, KafkaTopic>
     */
    private function extractTopicsFromString(?string $topics): array
    {
        if (null === $topics || '' === str_replace([',', ' '], '', $topics)) {
            return KafkaTopic::cases();
        }

        $results = [];

        foreach (explode(',', $topics) as $topicString) {
            $results[] = KafkaTopic::case(trim($topicString));
        }

        return $results;
    }

    private function onStart(KafkaTopic $topic): void
    {
        if (null === ($io = $this->io)) {
            return;
        }

        $io->info(\sprintf('Starting listening on %s...', $topic->name));
    }
}
