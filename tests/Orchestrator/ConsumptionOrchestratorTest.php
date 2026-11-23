<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Orchestrator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RdKafka\Message as RdKafkaMessage;
use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Factory\MessageFactoryInterface;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Orchestrator\ConsumptionOrchestrator;
use Rossel\RosselKafka\Service\Connector\KafkaConnectorInterface;

/**
 * ConsumptionOrchestrator::listen() runs an infinite loop.
 * Tests break the loop by configuring the mocked KafkaConsumer::consume()
 * to throw a \RuntimeException after a controlled number of calls.
 * Each test wraps listen() in a try/catch and asserts on side-effects.
 */
final class ConsumptionOrchestratorTest extends TestCase
{
    /** @var MockObject&LoggerInterface */
    private MockObject $logger;

    /** @var MockObject&KafkaConnectorInterface */
    private MockObject $connector;

    /** @var MockObject&MessageFactoryInterface */
    private MockObject $factory;

    /** @var MockObject&RdKafkaConsumer */
    private MockObject $rdKafkaConsumer;

    private Topic $topic;

    private Message $message;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->connector = $this->createMock(KafkaConnectorInterface::class);
        $this->factory = $this->createMock(MessageFactoryInterface::class);
        $this->rdKafkaConsumer = $this->createMock(RdKafkaConsumer::class);

        $this->topic = new Topic(
            configKey: TopicConfigKeys::KAFKA_TOPIC_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
            name: 'log.output.topic',
            messageTypes: [MessageType::EXEC_SUCCESS, MessageType::EXEC_ERROR, MessageType::LOG],
        );

        $this->message = new Message(
            new MessageHeaders(
                area: Area::FRANCE,
                from: 'test-app',
                messageType: MessageType::LOG,
                trackId: 'test-track-id',
            ),
            'EXEC_SUCCESS',
        );

        // Default: connector creates the consumer
        $this->connector
            ->method('createConsumer')
            ->with($this->topic)
            ->willReturn($this->rdKafkaConsumer);
    }

    // -------------------------------------------------------------------------
    // onStartCallable
    // -------------------------------------------------------------------------

    #[Test]
    public function onStartCallableIsCalledBeforeConsumerCreation(): void
    {
        $called = false;
        $this->configureReceiveToBreakAfter(0);

        try {
            $this->buildOrchestrator()->listen(
                $this->topic,
                onStartCallable: function (Topic $t) use (&$called): void {
                    $called = true;
                    self::assertSame($this->topic, $t);
                },
            );
        } catch (\RuntimeException) {
        }

        self::assertTrue($called);
    }

    #[Test]
    public function listenWorksWithoutOnStartCallable(): void
    {
        $this->configureReceiveToBreakAfter(0);

        try {
            $this->buildOrchestrator()->listen($this->topic);
        } catch (\RuntimeException) {
        }

        // No exception thrown before the loop break = pass
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Idle (no message)
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{int}>
     */
    public static function noMessageErrorProvider(): iterable
    {
        yield 'timed out' => [\RD_KAFKA_RESP_ERR__TIMED_OUT];
        yield 'partition EOF' => [\RD_KAFKA_RESP_ERR__PARTITION_EOF];
        yield 'transport' => [\RD_KAFKA_RESP_ERR__TRANSPORT];
    }

    #[Test]
    #[DataProvider('noMessageErrorProvider')]
    public function shouldCallOnIdleCallableWhenNoMessageIsConsumed(int $error): void
    {
        $idleCalled = false;
        $this->configureReceiveToBreakAfterOneMessage($this->kafkaMessage('', $error));
        $this->factory->expects($this->never())->method('createMessageFromRdKafka');
        $this->rdKafkaConsumer->expects($this->never())->method('commit');

        try {
            $this->buildOrchestrator()->listen(
                $this->topic,
                onIdleCallable: function (Topic $t) use (&$idleCalled): void {
                    $idleCalled = true;
                },
            );
        } catch (\RuntimeException) {
        }

        self::assertTrue($idleCalled);
    }

    #[Test]
    public function shouldStopListeningWhenConsumeFails(): void
    {
        $this->configureReceiveToBreakAfterOneMessage($this->kafkaMessage('', \RD_KAFKA_RESP_ERR__FATAL));
        $this->rdKafkaConsumer->expects($this->never())->method('commit');

        $exception = $this->listenUntilLoopBreaks($this->buildOrchestrator());

        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame(\RD_KAFKA_RESP_ERR__FATAL, $exception->getCode());
        self::assertNotSame('Break infinite loop', $exception->getMessage());
    }

    #[Test]
    public function shouldLogListeningOnceWhileIdle(): void
    {
        $this->configureReceiveToBreakAfter(3);
        /** @var \ArrayObject<int, string> $infos */
        $infos = new \ArrayObject();
        $this->logger->method('info')->willReturnCallback(function (string $message) use ($infos): void {
            $infos[] = $message;
        });

        $this->listenUntilLoopBreaks($this->buildOrchestrator());

        self::assertCount(1, array_filter($infos->getArrayCopy(), static fn (string $m): bool => str_contains($m, 'now listening')));
    }

    // -------------------------------------------------------------------------
    // Message received and consumed
    // -------------------------------------------------------------------------

    #[Test]
    public function matchingConsumerIsInvokedForReceivedMessage(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('EXEC_SUCCESS');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);

        $this->factory
            ->expects($this->once())
            ->method('createMessageFromRdKafka')
            ->with($rdKafkaMessage)
            ->willReturn($this->message);

        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(true);
        $consumer->method('supportsTopic')->willReturn(true);
        $consumer->expects($this->once())->method('__invoke')->with($this->message);

        // connector->send() called for the log message
        $this->connector->expects($this->once())->method('send');

        $this->rdKafkaConsumer->expects($this->once())->method('commit');

        try {
            $this->buildOrchestrator([$consumer])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    #[Test]
    public function onMessageCallableIsCalledAfterSuccessfulConsumption(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('EXEC_SUCCESS');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);

        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);

        $messageCalled = false;

        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(true);
        $consumer->method('supportsTopic')->willReturn(true);
        $this->connector->method('send');

        try {
            $this->buildOrchestrator([$consumer])->listen(
                $this->topic,
                onMessageCallable: function (Topic $t, MessageType $type) use (&$messageCalled): void {
                    $messageCalled = true;
                    self::assertSame(MessageType::LOG, $type);
                },
            );
        } catch (\RuntimeException) {
        }

        self::assertTrue($messageCalled);
    }

    #[Test]
    public function messageIsAcknowledgedAfterProcessing(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('EXEC_SUCCESS');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);

        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $this->connector->method('send');

        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(true);
        $consumer->method('supportsTopic')->willReturn(true);

        $this->rdKafkaConsumer->expects($this->once())->method('commit')->with($rdKafkaMessage);

        try {
            $this->buildOrchestrator([$consumer])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    // -------------------------------------------------------------------------
    // Non-matching consumers
    // -------------------------------------------------------------------------

    #[Test]
    public function consumerNotCalledWhenMessageTypeNotSupported(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('body');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $this->connector->method('send');

        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(false);
        $consumer->expects($this->never())->method('__invoke');

        try {
            $this->buildOrchestrator([$consumer])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    #[Test]
    public function consumerNotCalledWhenTopicNotSupported(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('body');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $this->connector->method('send');

        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(true);
        $consumer->method('supportsTopic')->willReturn(false);
        $consumer->expects($this->never())->method('__invoke');

        try {
            $this->buildOrchestrator([$consumer])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    #[Test]
    public function errorIsLoggedWhenNoConsumerMatchesMessage(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('body');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $this->connector->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        try {
            $this->buildOrchestrator([])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    #[Test]
    public function firstMatchingConsumerStopsIterationOverRemainingConsumers(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('body');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $this->connector->method('send');

        /** @var MockObject&ConsumerInterface $firstConsumer */
        $firstConsumer = $this->createMock(ConsumerInterface::class);
        $firstConsumer->method('supportsMessageType')->willReturn(true);
        $firstConsumer->method('supportsTopic')->willReturn(true);
        $firstConsumer->expects($this->once())->method('__invoke');

        /** @var MockObject&ConsumerInterface $secondConsumer */
        $secondConsumer = $this->createMock(ConsumerInterface::class);
        $secondConsumer->expects($this->never())->method('supportsMessageType');
        $secondConsumer->expects($this->never())->method('__invoke');

        try {
            $this->buildOrchestrator([$firstConsumer, $secondConsumer])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    // -------------------------------------------------------------------------
    // Log message (sendLogMessage)
    // -------------------------------------------------------------------------

    #[Test]
    public function successLogMessageSentAfterSuccessfulConsumption(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('body');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);

        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(true);
        $consumer->method('supportsTopic')->willReturn(true);

        $this->connector
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->identicalTo($this->topic),
                $this->callback(function (Message $logMessage): bool {
                    return 'EXEC_SUCCESS' === $logMessage->getBody()
                        && MessageType::LOG === $logMessage->getType();
                }),
            );

        try {
            $this->buildOrchestrator([$consumer])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    #[Test]
    public function shouldNotSendLogMessageWhenDisabled(): void
    {
        $this->configureReceiveToBreakAfterOneMessage($this->kafkaMessage('body'));
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(true);
        $consumer->method('supportsTopic')->willReturn(true);
        $sendCount = 0;
        $this->connector->method('send')->willReturnCallback(function () use (&$sendCount): void {
            ++$sendCount;
        });
        $orchestrator = $this->buildOrchestrator([$consumer], sendLogMessages: false);

        try {
            $orchestrator->listen($this->topic);
        } catch (\RuntimeException) {
        }

        self::assertSame(0, $sendCount);
    }

    #[Test]
    public function shouldSendOnlySuccessLogMessageWhenSecondConsumerMatches(): void
    {
        $this->configureReceiveToBreakAfterOneMessage($this->kafkaMessage('body'));
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $sentBodies = $this->spySentBodies();
        $orchestrator = $this->buildOrchestrator([$this->consumer(matches: false), $this->consumer(matches: true)]);

        $this->listenUntilLoopBreaks($orchestrator);

        self::assertSame(['EXEC_SUCCESS'], $sentBodies->getArrayCopy());
    }

    #[Test]
    public function shouldSendOneErrorLogMessageWhenNoConsumerMatches(): void
    {
        $this->configureReceiveToBreakAfterOneMessage($this->kafkaMessage('body'));
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $sentBodies = $this->spySentBodies();
        $orchestrator = $this->buildOrchestrator([$this->consumer(matches: false), $this->consumer(matches: false)]);

        $this->listenUntilLoopBreaks($orchestrator);

        self::assertSame(['EXEC_ERROR'], $sentBodies->getArrayCopy());
    }

    // -------------------------------------------------------------------------
    // Failing messages
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldKeepListeningWhenConsumerThrows(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('body');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);
        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $consumer = $this->consumer(matches: true);
        $consumer->method('__invoke')->willThrowException(new \LogicException('Consumer failure'));
        $sentBodies = $this->spySentBodies();
        $errors = $this->spyLoggedErrors();
        $acknowledged = $this->spyAcknowledgedMessages();

        $exception = $this->listenUntilLoopBreaks($this->buildOrchestrator([$consumer]));

        self::assertSame('Break infinite loop', $exception?->getMessage());
        self::assertSame(['EXEC_ERROR'], $sentBodies->getArrayCopy());
        self::assertCount(1, $errors);
        self::assertSame([$rdKafkaMessage], $acknowledged->getArrayCopy());
    }

    #[Test]
    public function shouldKeepListeningWhenMessageHeadersAreInvalid(): void
    {
        $rdKafkaMessage = $this->kafkaMessage('body');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);
        $this->factory->method('createMessageFromRdKafka')->willThrowException(new \InvalidArgumentException('Invalid headers'));
        $sentBodies = $this->spySentBodies();
        $errors = $this->spyLoggedErrors();
        $acknowledged = $this->spyAcknowledgedMessages();

        $exception = $this->listenUntilLoopBreaks($this->buildOrchestrator([$this->consumer(matches: true)]));

        self::assertSame('Break infinite loop', $exception?->getMessage());
        self::assertSame([], $sentBodies->getArrayCopy());
        self::assertCount(1, $errors);
        self::assertSame([$rdKafkaMessage], $acknowledged->getArrayCopy());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param list<ConsumerInterface> $consumers
     */
    private function buildOrchestrator(array $consumers = [], bool $sendLogMessages = true): ConsumptionOrchestrator
    {
        return new ConsumptionOrchestrator(
            logger: $this->logger,
            kafkaConnector: $this->connector,
            messageFactory: $this->factory,
            consumers: $consumers,
            appName: 'test-app',
            sendLogMessages: $sendLogMessages,
        );
    }

    /**
     * Configure consume() to throw after N idle (timed out) calls.
     */
    private function configureReceiveToBreakAfter(int $nullCallsBeforeBreak): void
    {
        $callCount = 0;
        $this->rdKafkaConsumer
            ->method('consume')
            ->willReturnCallback(function () use (&$callCount, $nullCallsBeforeBreak): RdKafkaMessage {
                if ($callCount < $nullCallsBeforeBreak) {
                    ++$callCount;

                    return $this->kafkaMessage('', \RD_KAFKA_RESP_ERR__TIMED_OUT);
                }
                throw new \RuntimeException('Break infinite loop');
            });
    }

    private function kafkaMessage(string $payload, int $error = \RD_KAFKA_RESP_ERR_NO_ERROR): RdKafkaMessage
    {
        $message = new RdKafkaMessage();
        $message->err = $error;
        $message->payload = $payload;
        $message->partition = 0;
        $message->offset = 42;

        return $message;
    }

    /**
     * Configure consume() to return a message on the first call, then break.
     */
    private function configureReceiveToBreakAfterOneMessage(RdKafkaMessage $rdKafkaMessage): void
    {
        $callCount = 0;
        $this->rdKafkaConsumer
            ->method('consume')
            ->willReturnCallback(function () use (&$callCount, $rdKafkaMessage): RdKafkaMessage {
                ++$callCount;

                if (1 === $callCount) {
                    return $rdKafkaMessage;
                }

                throw new \RuntimeException('Break infinite loop');
            });
    }

    /**
     * @return MockObject&ConsumerInterface
     */
    private function consumer(bool $matches): MockObject
    {
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn($matches);
        $consumer->method('supportsTopic')->willReturn($matches);

        return $consumer;
    }

    /**
     * @return \ArrayObject<int, string>
     */
    private function spySentBodies(): \ArrayObject
    {
        /** @var \ArrayObject<int, string> $bodies */
        $bodies = new \ArrayObject();
        $this->connector->method('send')->willReturnCallback(function (Topic $topic, Message $message) use ($bodies): void {
            $bodies[] = $message->getBody();
        });

        return $bodies;
    }

    /**
     * @return \ArrayObject<int, string>
     */
    private function spyLoggedErrors(): \ArrayObject
    {
        /** @var \ArrayObject<int, string> $errors */
        $errors = new \ArrayObject();
        $this->logger->method('error')->willReturnCallback(function (string $message) use ($errors): void {
            $errors[] = $message;
        });

        return $errors;
    }

    /**
     * @return \ArrayObject<int, RdKafkaMessage>
     */
    private function spyAcknowledgedMessages(): \ArrayObject
    {
        /** @var \ArrayObject<int, RdKafkaMessage> $acknowledged */
        $acknowledged = new \ArrayObject();
        $this->rdKafkaConsumer->method('commit')->willReturnCallback(function (RdKafkaMessage $message) use ($acknowledged): void {
            $acknowledged[] = $message;
        });

        return $acknowledged;
    }

    private function listenUntilLoopBreaks(ConsumptionOrchestrator $orchestrator): ?\Throwable
    {
        try {
            $orchestrator->listen($this->topic);
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }
}
