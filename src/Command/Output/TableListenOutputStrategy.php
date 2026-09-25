<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Command\Output;

use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Topic;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Streams each log message as a table row — no cursor movement, no frames, no flicker.
 *
 * Output format:
 *
 *   ┌──────────┬──────────────────────────────────────────────────────────────┬────────────────────────────────────────────────────────────────────────┐
 *   │ HH:MM:SS │ topic-name                                               │ message content                                                        │
 *   ├──────────┼──────────────────────────────────────────────────────────┼────────────────────────────────────────────────────────────────────────┤
 *   │ HH:MM:SS │ a-very-long-topic-name-that-wraps-onto-the-next-line     │ a very long message that wraps automatically onto the next line        │
 *   │          │ continuation-of-topic-name                               │ without truncating any content                                         │
 *
 * Each topic is assigned a distinct color on first appearance.
 */
final class TableListenOutputStrategy implements ListenOutputStrategyInterface
{
    /** @var list<string> */
    private const COLORS = [
        'bright-cyan',
        'bright-yellow',
        'bright-green',
        'bright-magenta',
        'bright-blue',
        'bright-red',
        'cyan',
        'yellow',
        'green',
        'magenta',
    ];

    private const TIME_WIDTH = 8;  // HH:MM:SS
    private const TOPIC_WIDTH = 60;
    private const MSG_WIDTH = 70;

    /** @var array<string, string> topic name → Symfony color tag */
    private array $topicColors = [];
    private int $nextColor = 0;
    private bool $firstRow = true;

    public function __construct(private readonly OutputInterface $output)
    {
        // A previous run using the frame-based dashboard may have left the
        // terminal in alternate screen mode (\033[?1049h) with the cursor hidden
        // (\033[?25l). Reset both unconditionally so scrollback works normally.
        if ($output->isDecorated()) {
            $output->write("\033[?25h\033[?1049l", false, OutputInterface::OUTPUT_RAW);
        }
    }

    public function onStart(Topic $topic): void
    {
        // Pre-assign the color so multi-topic parent processes can register
        // all topics before the first message arrives.
        $this->colorFor($topic->getName());
    }

    public function onIdle(Topic $topic): void
    {
        // No output between messages.
    }

    public function onMessage(Topic $topic, MessageType $messageType): void
    {
        $this->printRow(date('H:i:s'), $topic->getName(), $messageType->name);
    }

    public function warning(string $message): void
    {
        $this->output->writeln('<comment>Warning: '.$message.'</comment>');
    }

    /**
     * Formats and prints one table row.
     * Called directly by ListenCommand for multi-topic child-process output.
     */
    public function printRow(string $time, string $topicName, string $message): void
    {
        $color = $this->colorFor($topicName);

        $tw = self::TIME_WIDTH;
        $tpw = self::TOPIC_WIDTH;
        $mw = self::MSG_WIDTH;

        // First row gets a top border; subsequent rows get a mid-separator.
        if ($this->firstRow) {
            $this->firstRow = false;
            $this->output->writeln(
                '┌'.str_repeat('─', $tw + 2).'┬'.str_repeat('─', $tpw + 2).'┬'.str_repeat('─', $mw + 2).'┐',
            );
        } else {
            $this->output->writeln(
                '├'.str_repeat('─', $tw + 2).'┼'.str_repeat('─', $tpw + 2).'┼'.str_repeat('─', $mw + 2).'┤',
            );
        }

        $timePad = str_repeat(' ', $tw - mb_strlen($time));
        $topicLines = $this->wrapText($topicName, $tpw);
        $msgLines = $this->wrapText($message, $mw);
        $rowCount = max(\count($topicLines), \count($msgLines));

        for ($i = 0; $i < $rowCount; ++$i) {
            $topicLine = $topicLines[$i] ?? '';
            $msgLine = $msgLines[$i] ?? '';
            $topicPad = str_repeat(' ', $tpw - mb_strlen($topicLine));
            $linePad = str_repeat(' ', $mw - mb_strlen($msgLine));

            if (0 === $i) {
                $this->output->writeln(
                    '│ '.$time.$timePad
                    .' │ <fg='.$color.'>'.$topicLine.'</>'.$topicPad
                    .' │ '.$msgLine.$linePad.' │',
                );
            } else {
                $this->output->writeln(
                    '│ '.str_repeat(' ', $tw)
                    .' │ <fg='.$color.'>'.$topicLine.'</>'.$topicPad
                    .' │ '.$msgLine.$linePad.' │',
                );
            }
        }
    }

    private function colorFor(string $topicName): string
    {
        if (!isset($this->topicColors[$topicName])) {
            $this->topicColors[$topicName] = self::COLORS[$this->nextColor % \count(self::COLORS)];
            ++$this->nextColor;
        }

        return $this->topicColors[$topicName];
    }

    /**
     * Wraps $text into lines of at most $maxLen characters.
     * Breaks at the last space within the limit when possible.
     *
     * @return list<string>
     */
    private function wrapText(string $text, int $maxLen): array
    {
        if (mb_strlen($text) <= $maxLen) {
            return [$text];
        }

        $lines = [];

        while (mb_strlen($text) > $maxLen) {
            $chunk = mb_substr($text, 0, $maxLen);
            $lastSpace = mb_strrpos($chunk, ' ');

            if (false !== $lastSpace && $lastSpace > (int) ($maxLen * 0.4)) {
                $lines[] = mb_substr($text, 0, $lastSpace);
                $text = mb_substr($text, $lastSpace + 1);
            } else {
                $lines[] = $chunk;
                $text = mb_substr($text, $maxLen);
            }
        }

        if ('' !== $text) {
            $lines[] = $text;
        }

        return $lines;
    }
}
