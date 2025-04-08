<?php

namespace Rossel\RosselKafka\Command;

use Rossel\RosselKafka\Orchestrator\ConsumptionOrchestrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ListenCommand extends Command
{
    public function __construct(
        private ConsumptionOrchestrator $consumptionOrchestrator
    )
    {
        parent::__construct(
            name: 'rossel:kafka:listen',
        );
    }

    protected function configure()
    {
        $this->setDescription('Launch Kafka listeners');
        $this->addOption(
            name: 'topics',
            shortcut: 't',
            mode: InputOption::VALUE_IS_ARRAY,
            description: 'Topics to listen to'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $topics = $input->getOption('topics');
        dd($topics);

        $this->consumptionOrchestrator->listen();

        return Command::SUCCESS;
    }
}
