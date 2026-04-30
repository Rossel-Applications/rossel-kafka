<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Command\Output;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Command\Output\TableListenOutputStrategy;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;
use Symfony\Component\Console\Output\OutputInterface;

final class TableListenOutputStrategyTest extends TestCase
{
    /** @var MockObject&OutputInterface */
    private MockObject $output;

    private TableListenOutputStrategy $strategy;

    private Topic $topic;

    protected function setUp(): void
    {
        $this->output = $this->createMock(OutputInterface::class);
        // isDecorated() = false avoids the ANSI escape write in constructor
        $this->output->method('isDecorated')->willReturn(false);

        $this->strategy = new TableListenOutputStrategy($this->output);

        $this->topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output',
        );
    }

    // -------------------------------------------------------------------------
    // Constructor — ANSI reset
    // -------------------------------------------------------------------------

    #[Test]
    public function constructorWritesAnsiResetWhenOutputIsDecorated(): void
    {
        /** @var MockObject&OutputInterface $decoratedOutput */
        $decoratedOutput = $this->createMock(OutputInterface::class);
        $decoratedOutput->method('isDecorated')->willReturn(true);
        $decoratedOutput
            ->expects($this->once())
            ->method('write')
            ->with($this->stringContains("\033["), false, OutputInterface::OUTPUT_RAW);

        new TableListenOutputStrategy($decoratedOutput);
    }

    #[Test]
    public function constructorDoesNotWriteWhenOutputIsNotDecorated(): void
    {
        $this->output->expects($this->never())->method('write');

        // strategy already constructed in setUp with isDecorated=false
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // onStart
    // -------------------------------------------------------------------------

    #[Test]
    public function onStartRegistersColorForTopic(): void
    {
        // onStart should not produce any output — it only pre-registers the color
        $this->output->expects($this->never())->method('writeln');

        $this->strategy->onStart($this->topic);
    }

    // -------------------------------------------------------------------------
    // onIdle
    // -------------------------------------------------------------------------

    #[Test]
    public function onIdleProducesNoOutput(): void
    {
        $this->output->expects($this->never())->method('writeln');
        $this->output->expects($this->never())->method('write');

        $this->strategy->onIdle($this->topic);
    }

    // -------------------------------------------------------------------------
    // onMessage
    // -------------------------------------------------------------------------

    #[Test]
    public function onMessageCallsPrintRow(): void
    {
        $this->output->expects($this->atLeastOnce())->method('writeln');

        $this->strategy->onMessage($this->topic, MessageType::EXEC_SUCCESS);
    }

    // -------------------------------------------------------------------------
    // warning
    // -------------------------------------------------------------------------

    #[Test]
    public function warningWritesCommentTaggedMessage(): void
    {
        $this->output
            ->expects($this->once())
            ->method('writeln')
            ->with('<comment>Warning: disk full</comment>');

        $this->strategy->warning('disk full');
    }

    // -------------------------------------------------------------------------
    // printRow — border rendering
    // -------------------------------------------------------------------------

    #[Test]
    public function firstRowGetTopBorder(): void
    {
        $writes = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$writes): void {
                $writes[] = $line;
            });

        $this->strategy->printRow('12:00:00', 'my-topic', 'hello');

        self::assertStringStartsWith('┌', $writes[0], 'First row must open with top border ┌');
    }

    #[Test]
    public function secondRowGetsMidSeparator(): void
    {
        $writes = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$writes): void {
                $writes[] = $line;
            });

        $this->strategy->printRow('12:00:00', 'topic', 'first');
        $writesAfterFirst = \count($writes);

        $this->strategy->printRow('12:00:01', 'topic', 'second');

        self::assertStringStartsWith('├', $writes[$writesAfterFirst], 'Subsequent rows start with mid separator ├');
    }

    #[Test]
    public function printRowContainsTimeTopicAndMessage(): void
    {
        $lines = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $this->strategy->printRow('09:30:00', 'my-topic', 'my-message');

        $allOutput = implode("\n", $lines);
        self::assertStringContainsString('09:30:00', $allOutput);
        self::assertStringContainsString('my-topic', $allOutput);
        self::assertStringContainsString('my-message', $allOutput);
    }

    // -------------------------------------------------------------------------
    // printRow — color assignment
    // -------------------------------------------------------------------------

    #[Test]
    public function differentTopicsGetDifferentColors(): void
    {
        $lines = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $this->strategy->printRow('12:00:00', 'topic-a', 'msg');
        $this->strategy->printRow('12:00:01', 'topic-b', 'msg');

        $firstTopicLine = array_values(array_filter($lines, fn ($l) => str_contains($l, 'topic-a')))[0] ?? '';
        $secondTopicLine = array_values(array_filter($lines, fn ($l) => str_contains($l, 'topic-b')))[0] ?? '';

        // Each topic wrapped in a different color fg= tag
        preg_match('/<fg=([^>]+)>topic-a/', $firstTopicLine, $matchA);
        preg_match('/<fg=([^>]+)>topic-b/', $secondTopicLine, $matchB);

        self::assertNotEmpty($matchA, 'topic-a should have a color tag');
        self::assertNotEmpty($matchB, 'topic-b should have a color tag');
        self::assertNotSame($matchA[1], $matchB[1], 'Different topics get different colors');
    }

    #[Test]
    public function sameTopicAlwaysGetsTheSameColor(): void
    {
        $lines = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $this->strategy->printRow('12:00:00', 'stable-topic', 'first');
        $this->strategy->printRow('12:00:01', 'stable-topic', 'second');

        $topicLines = array_values(array_filter($lines, fn ($l) => str_contains($l, 'stable-topic')));

        preg_match('/<fg=([^>]+)>stable-topic/', $topicLines[0], $match1);
        preg_match('/<fg=([^>]+)>stable-topic/', $topicLines[1], $match2);

        self::assertSame($match1[1] ?? 'a', $match2[1] ?? 'b', 'Same topic must keep same color');
    }

    #[Test]
    public function colorsWrapAroundAfterTenTopics(): void
    {
        $lines = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        // 11 distinct topics — the 11th should cycle back to the first color
        for ($i = 1; $i <= 11; ++$i) {
            $this->strategy->printRow('12:00:00', "topic-{$i}", 'msg');
        }

        $colorOf = function (string $topic) use ($lines): string {
            foreach ($lines as $line) {
                if (preg_match('/<fg=([^>]+)>'.$topic.'<\/>/', $line, $m)) {
                    return $m[1];
                }
            }

            return '';
        };

        self::assertSame($colorOf('topic-1'), $colorOf('topic-11'), 'Color should cycle after 10 topics');
    }

    // -------------------------------------------------------------------------
    // wrapText (via printRow)
    // -------------------------------------------------------------------------

    #[Test]
    public function shortTextFitsInOneRow(): void
    {
        $writes = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$writes): void {
                $writes[] = $line;
            });

        $this->strategy->printRow('12:00:00', 'topic', 'short');

        // border + 1 data line = 2 writeln calls
        self::assertCount(2, $writes);
    }

    #[Test]
    public function longMessageWrapsIntoMultipleRows(): void
    {
        $writes = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$writes): void {
                $writes[] = $line;
            });

        // 150 chars > MSG_WIDTH (70) → wraps to at least 2 data lines
        $longMsg = str_repeat('A', 150);
        $this->strategy->printRow('12:00:00', 'topic', $longMsg);

        // border + at least 2 data lines
        self::assertGreaterThanOrEqual(3, \count($writes));
    }

    #[Test]
    public function longTopicNameWrapsIntoMultipleRows(): void
    {
        $writes = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$writes): void {
                $writes[] = $line;
            });

        // 80 chars > TOPIC_WIDTH (60) → wraps
        $longTopic = str_repeat('t', 80);
        $this->strategy->printRow('12:00:00', $longTopic, 'short msg');

        self::assertGreaterThanOrEqual(3, \count($writes));
    }

    #[Test]
    public function wrapTextBreaksAtLastSpaceWhenPossible(): void
    {
        $writes = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$writes): void {
                $writes[] = $line;
            });

        // Build a 75-char message with a space at position 40
        $msg = str_repeat('a', 40).' '.str_repeat('b', 34);
        $this->strategy->printRow('12:00:00', 'topic', $msg);

        $allOutput = implode("\n", $writes);
        // The first part before the space should appear
        self::assertStringContainsString(str_repeat('a', 40), $allOutput);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function wrapLengthProvider(): iterable
    {
        yield 'exactly at limit (60 chars)' => [str_repeat('x', 60), 2];  // border + 1 data
        yield 'one over limit (61 chars)' => [str_repeat('x', 61), 3];    // border + 2 data lines
        yield 'empty string' => ['', 2];                                   // border + 1 data
    }

    #[Test]
    #[DataProvider('wrapLengthProvider')]
    public function topicWrapThreshold(string $topicName, int $minLines): void
    {
        $writes = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$writes): void {
                $writes[] = $line;
            });

        $this->strategy->printRow('12:00:00', $topicName, 'msg');

        self::assertGreaterThanOrEqual($minLines, \count($writes));
    }
}
