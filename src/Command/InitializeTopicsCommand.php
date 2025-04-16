<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Command;

use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class InitializeTopicsCommand extends Command
{
    private const COMMAND_NAME = 'rossel:kafka:initialize-topics';
    private const COMMAND_DESCRIPTION = 'Initializes topics';

    private ?SymfonyStyle $io = null;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            name: self::COMMAND_NAME,
        );
    }

    protected function configure(): void
    {
        $this->setDescription(self::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $processes = [];

        foreach (KafkaTopic::cases() as $topic) {
            $process = new Process([
                './kafka-topics.sh',
                '--bootstrap-server=localhost:9092',
                '--create',
                \sprintf('--topic=%s', $topic->name),
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
}
