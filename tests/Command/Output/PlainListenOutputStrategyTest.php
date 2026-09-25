<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Command\Output;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Command\Output\PlainListenOutputStrategy;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;
use Symfony\Component\Console\Output\OutputInterface;

final class PlainListenOutputStrategyTest extends TestCase
{
    /** @var MockObject&OutputInterface */
    private MockObject $output;

    private PlainListenOutputStrategy $strategy;

    private Topic $topic;

    protected function setUp(): void
    {
        $this->output = $this->createMock(OutputInterface::class);
        $this->strategy = new PlainListenOutputStrategy($this->output);
        $this->topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'public.log.output',
        );
    }

    #[Test]
    public function onStartWritesStartingMessage(): void
    {
        $this->output
            ->expects($this->once())
            ->method('writeln')
            ->with('Starting listening on public.log.output...');

        $this->strategy->onStart($this->topic);
    }

    #[Test]
    public function onIdleProducesNoOutput(): void
    {
        $this->output->expects($this->never())->method('writeln');
        $this->output->expects($this->never())->method('write');

        $this->strategy->onIdle($this->topic);
    }

    /**
     * @return iterable<string, array{MessageType}>
     */
    public static function messageTypeProvider(): iterable
    {
        yield 'LOG' => [MessageType::LOG];
        yield 'EXEC_SUCCESS' => [MessageType::EXEC_SUCCESS];
        yield 'EXEC_ERROR' => [MessageType::EXEC_ERROR];
        yield 'CANCEL_B2C_SUBSCRIPTION' => [MessageType::CANCEL_B2C_SUBSCRIPTION];
    }

    #[Test]
    #[DataProvider('messageTypeProvider')]
    public function onMessageWritesEventReceivedLine(MessageType $type): void
    {
        $this->output
            ->expects($this->once())
            ->method('writeln')
            ->with('Event received: '.$type->name);

        $this->strategy->onMessage($this->topic, $type);
    }

    #[Test]
    public function warningWritesWarningPrefixedMessage(): void
    {
        $this->output
            ->expects($this->once())
            ->method('writeln')
            ->with('Warning: something went wrong');

        $this->strategy->warning('something went wrong');
    }

    #[Test]
    public function onStartIncludesTopicName(): void
    {
        $topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE,
            name: 'my.custom.topic',
        );

        $this->output
            ->expects($this->once())
            ->method('writeln')
            ->with('Starting listening on my.custom.topic...');

        $this->strategy->onStart($topic);
    }
}
