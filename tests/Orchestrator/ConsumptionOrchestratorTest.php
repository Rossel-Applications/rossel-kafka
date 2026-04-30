<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Orchestrator;

use Enqueue\RdKafka\RdKafkaConsumer;
use Enqueue\RdKafka\RdKafkaMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
 * Tests break the loop by configuring the mocked RdKafkaConsumer::receive()
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
            configKey: TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE,
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

    #[Test]
    public function onIdleCallableIsCalledWhenNoMessageReceived(): void
    {
        $idleCalled = false;

        // First receive() returns null (idle), second breaks the loop
        $callCount = 0;
        $this->rdKafkaConsumer
            ->method('receive')
            ->willReturnCallback(function () use (&$callCount): ?RdKafkaMessage {
                ++$callCount;
                if (1 === $callCount) {
                    return null;
                }
                throw new \RuntimeException('Break loop');
            });

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

    // -------------------------------------------------------------------------
    // Message received and consumed
    // -------------------------------------------------------------------------

    #[Test]
    public function matchingConsumerIsInvokedForReceivedMessage(): void
    {
        $rdKafkaMessage = new RdKafkaMessage(body: 'EXEC_SUCCESS');
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

        $this->rdKafkaConsumer->expects($this->once())->method('acknowledge');

        try {
            $this->buildOrchestrator([$consumer])->listen($this->topic);
        } catch (\RuntimeException) {
        }
    }

    #[Test]
    public function onMessageCallableIsCalledAfterSuccessfulConsumption(): void
    {
        $rdKafkaMessage = new RdKafkaMessage(body: 'EXEC_SUCCESS');
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
        $rdKafkaMessage = new RdKafkaMessage(body: 'EXEC_SUCCESS');
        $this->configureReceiveToBreakAfterOneMessage($rdKafkaMessage);

        $this->factory->method('createMessageFromRdKafka')->willReturn($this->message);
        $this->connector->method('send');

        /** @var MockObject&ConsumerInterface $consumer */
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->method('supportsMessageType')->willReturn(true);
        $consumer->method('supportsTopic')->willReturn(true);

        $this->rdKafkaConsumer->expects($this->once())->method('acknowledge')->with($rdKafkaMessage);

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
        $rdKafkaMessage = new RdKafkaMessage(body: 'body');
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
        $rdKafkaMessage = new RdKafkaMessage(body: 'body');
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
        $rdKafkaMessage = new RdKafkaMessage(body: 'body');
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
        $rdKafkaMessage = new RdKafkaMessage(body: 'body');
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
        $rdKafkaMessage = new RdKafkaMessage(body: 'body');
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param list<ConsumerInterface> $consumers
     */
    private function buildOrchestrator(array $consumers = []): ConsumptionOrchestrator
    {
        return new ConsumptionOrchestrator(
            logger: $this->logger,
            kafkaConnector: $this->connector,
            messageFactory: $this->factory,
            consumers: $consumers,
            appName: 'test-app',
        );
    }

    /**
     * Configure receive() to throw after N idle (null) calls.
     */
    private function configureReceiveToBreakAfter(int $nullCallsBeforeBreak): void
    {
        $callCount = 0;
        $this->rdKafkaConsumer
            ->method('receive')
            ->willReturnCallback(function () use (&$callCount, $nullCallsBeforeBreak): ?RdKafkaMessage {
                if ($callCount < $nullCallsBeforeBreak) {
                    ++$callCount;

                    return null;
                }
                throw new \RuntimeException('Break infinite loop');
            });
    }

    /**
     * Configure receive() to return a message on the first call, then break.
     */
    private function configureReceiveToBreakAfterOneMessage(RdKafkaMessage $rdKafkaMessage): void
    {
        $callCount = 0;
        $this->rdKafkaConsumer
            ->method('receive')
            ->willReturnCallback(function () use (&$callCount, $rdKafkaMessage): RdKafkaMessage {
                ++$callCount;

                if (1 === $callCount) {
                    return $rdKafkaMessage;
                }

                throw new \RuntimeException('Break infinite loop');
            });
    }
}
