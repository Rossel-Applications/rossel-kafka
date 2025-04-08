<?php

namespace Rossel\RosselKafka\Command;

use Rossel\RosselKafka\Manager\ConsumptionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ListenCommand extends Command
{
    public function __construct(
        private ConsumptionManager $consumptionManager
    )
    {
        parent::__construct(
            name: 'rossel:kafka:listen',
        );
    }

    protected function configure()
    {
        $this->setDescription('Launch Kafka listeners');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {


        return Command::SUCCESS;
    }
}
