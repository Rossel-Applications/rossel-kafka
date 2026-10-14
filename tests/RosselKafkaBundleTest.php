<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\Topic;
use Rossel\RosselKafka\Orchestrator\ConsumptionOrchestrator;
use Rossel\RosselKafka\RosselKafkaBundle;
use Rossel\RosselKafka\Service\Connector\KafkaConnector;
use Rossel\RosselKafka\Service\KafkaTopicsFetcher;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RosselKafkaBundleTest extends TestCase
{
    private RosselKafkaBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new RosselKafkaBundle();
    }

    #[Test]
    public function bundleNameConstantIsCorrect(): void
    {
        self::assertSame('rossel_kafka', RosselKafkaBundle::BUNDLE_NAME);
    }

    #[Test]
    public function shouldWireServicesFromBundleConfiguration(): void
    {
        $container = $this->buildContainer([
            'broker' => [
                'url' => 'kafka://localhost:9092',
                'authentication' => ['sasl_username' => 'user', 'sasl_password' => 'secret'],
                'topics' => ['public_offer_output_v1_json_delete' => 'offer.output'],
            ],
            'producer' => ['app_name' => 'test-app', 'send_log_messages' => false],
        ]);

        $container->compile();

        $orchestrator = $container->get(ConsumptionOrchestrator::class);
        $topicsFetcher = $container->get(KafkaTopicsFetcher::class);
        $connector = $container->get(KafkaConnector::class);
        self::assertInstanceOf(ConsumptionOrchestrator::class, $orchestrator);
        self::assertInstanceOf(KafkaTopicsFetcher::class, $topicsFetcher);
        self::assertInstanceOf(KafkaConnector::class, $connector);
        /** @var iterable<ConsumerInterface> $consumerIterator */
        $consumerIterator = $this->property($orchestrator, 'consumers');
        $consumers = iterator_to_array($consumerIterator, false);
        self::assertCount(1, $consumers);
        self::assertInstanceOf(DummyConsumer::class, $consumers[0]);
        self::assertSame('test-app', $this->property($orchestrator, 'appName'));
        self::assertFalse($this->property($orchestrator, 'sendLogMessages'));
        self::assertSame('offer.output', $topicsFetcher->get(TopicConfigKeys::KAFKA_TOPIC_PUBLIC_OFFER_OUTPUT_V1_JSON_DELETE)->getName());
        self::assertSame('user', $this->property($connector, 'saslUsername'));
        self::assertSame('secret', $this->property($connector, 'saslPassword'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->register(LoggerInterface::class, NullLogger::class);
        $container->register(DummyConsumer::class)->setAutoconfigured(true);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $definition) {
                    $definition->setPublic(true);
                }
            }
        });

        $extension = $this->bundle->getContainerExtension() ?? throw new \LogicException('The bundle has no extension.');
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), $config);

        return $container;
    }

    private function property(object $object, string $name): mixed
    {
        return (new \ReflectionProperty($object, $name))->getValue($object);
    }
}

final class DummyConsumer implements ConsumerInterface
{
    public function supportsTopic(Topic $topic): bool
    {
        return true;
    }

    public function supportsMessageType(Message $message): bool
    {
        return true;
    }

    public function __invoke(Message $message): void
    {
    }
}
