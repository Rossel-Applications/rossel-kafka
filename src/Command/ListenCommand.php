<?php

namespace Rossel\RosselKafka\Command;

use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
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
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Topics to listen to (separated by comma)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $topicsInput = $input->getOption('topics');

        $topics = $this->extractTopicsFromString($topicsInput);

        //$this->consumptionOrchestrator->listen();

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
}
