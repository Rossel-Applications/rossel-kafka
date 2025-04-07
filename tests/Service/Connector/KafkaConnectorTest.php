<?php

declare(strict_types=1);

namespace Service\Connector;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Enqueue\RdKafka\RdKafkaContext;
use Enqueue\RdKafka\RdKafkaProducer;
use Enqueue\RdKafka\RdKafkaTopic;
use Enqueue\RdKafka\RdKafkaMessage;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Model\MessageInterface;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;
use SplObjectStorage;

/**
 * Unit tests for KafkaConnector.
 */
final class KafkaConnectorTest extends TestCase
{
    private KafkaConnector $connector;
    private RdKafkaContext $mockContext;

    /** @var MockObject&RdKafkaProducer */
    private RdKafkaProducer $mockProducer;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->mockContext = $this->createMock(RdKafkaContext::class);
        $this->mockProducer = $this->createMock(RdKafkaProducer::class);
        $this->mockContext->method('createProducer')->willReturn($this->mockProducer);

        // Instantiate KafkaConnector without constructor
        $this->connector = (new ReflectionClass(KafkaConnector::class))
            ->newInstanceWithoutConstructor();

        // Manually inject mocks into readonly properties
        $this->setReadonlyProperty($this->connector, 'rdKafkaContext', $this->mockContext);
        $this->setReadonlyProperty($this->connector, 'rdKafkaProducer', $this->mockProducer);
        $this->setReadonlyProperty($this->connector, 'topics', new SplObjectStorage());
    }

    /**
     * @throws ReflectionException
     */
    private function setReadonlyProperty(object $object, string $propertyName, mixed $value): void
    {
        $property = new ReflectionProperty($object, $propertyName);
        $property->setValue($object, $value);
    }

    /**
     * @throws Exception
     * @throws InvalidMessageException
     * @throws \Interop\Queue\Exception
     */
    public function testSendWithRdKafkaTopic(): void
    {
        $this->expectNotToPerformAssertions();

        $topic = $this->createMock(RdKafkaTopic::class);
        $message = $this->createMock(MessageInterface::class);
        $rdKafkaMessage = $this->createMock(RdKafkaMessage::class);

        $message->method('getRdKafkaMessage')->willReturn($rdKafkaMessage);

        $this->connector->send($topic, $message);
    }

    /**
     * @throws Exception
     * @throws InvalidMessageException
     * @throws InvalidDestinationException
     * @throws \Interop\Queue\Exception
     * @throws ReflectionException
     */
    public function testSendWithKafkaTopicEnum(): void
    {
        $topicEnum = KafkaTopic::SYNC_ERP;
        $rdKafkaTopic = $this->createMock(RdKafkaTopic::class);
        $message = $this->createMock(MessageInterface::class);
        $rdKafkaMessage = $this->createMock(RdKafkaMessage::class);

        $message->method('getRdKafkaMessage')->willReturn($rdKafkaMessage);

        $topics = $this->connectorTopicsStorage();
        $topics[$topicEnum] = $rdKafkaTopic;

        $this->mockProducer
            ->expects(self::once())
            ->method('send')
            ->with($rdKafkaTopic, $rdKafkaMessage);

        $this->connector->send($topicEnum, $message);
    }

    /**
     * @throws Exception
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws \Interop\Queue\Exception
     */
    public function testSendThrowsInvalidArgumentExceptionWhenTopicNotRegistered(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Topic "SYNC_ERP" is not registered in the KafkaConnector.');

        $message = $this->createMock(MessageInterface::class);

        $this->connector->send(KafkaTopic::SYNC_ERP, $message);
    }

    /**
     * @throws ReflectionException
     */
    public function testBuildConnectionFactory(): void
    {
        $method = new ReflectionMethod($this->connector, 'buildConnectionFactory');

        /** @var RdKafkaConnectionFactory $factory */
        $factory = $method->invoke($this->connector, 'kafka://dummy-broker:9092');

        self::assertInstanceOf(RdKafkaConnectionFactory::class, $factory);

        $configProperty = new ReflectionProperty($factory, 'config');

        /** @var array<string, array<string, string>> $config */
        $config = $configProperty->getValue($factory);

        self::assertSame('dummy-broker:9092', $config['global']['metadata.broker.list']);
        self::assertSame('false', $config['global']['enable.auto.commit']);
        self::assertSame('beginning', $config['topic']['auto.offset.reset']);
        self::assertNotEmpty($config['global']['group.id']);
    }

    /**
     * @throws ReflectionException
     */
    public function testBuildContext(): void
    {
        $realConnector = new KafkaConnector('kafka://dummy-broker:9092');

        // Check private properties initialized correctly
        $topicsStorage = new ReflectionProperty($realConnector, 'topics');
        $topics = $topicsStorage->getValue($realConnector);

        self::assertInstanceOf(SplObjectStorage::class, $topics);
        self::assertCount(count(KafkaTopic::cases()), $topics);

        // Check if all Kafka topics were properly registered
        foreach (KafkaTopic::cases() as $case) {
            self::assertTrue($topics->contains($case));
            self::assertInstanceOf(RdKafkaTopic::class, $topics[$case]);
            self::assertSame($case->name, $topics[$case]->getTopicName());
        }
    }

    /**
     * @return SplObjectStorage<KafkaTopic, RdKafkaTopic>
     *
     * @throws ReflectionException
     */
    private function connectorTopicsStorage(): SplObjectStorage
    {
        /** @var SplObjectStorage<KafkaTopic, RdKafkaTopic> $topics */
        $topics = (new ReflectionProperty($this->connector, 'topics'))->getValue($this->connector);

        return $topics;
    }
}
