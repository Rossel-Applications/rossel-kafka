<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Command\Output;

use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class PlainListenOutputStrategy implements ListenOutputStrategyInterface
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function onStart(Topic $topic): void
    {
        $this->output->writeln(\sprintf('Starting listening on %s...', $topic->getName()));
    }

    public function onIdle(Topic $topic): void
    {
        // No output in non-interactive mode during idle ticks.
    }

    public function onMessage(Topic $topic, MessageType $messageType): void
    {
        $this->output->writeln(\sprintf('Event received: %s', $messageType->name));
    }

    public function warning(string $message): void
    {
        $this->output->writeln(\sprintf('Warning: %s', $message));
    }
}
