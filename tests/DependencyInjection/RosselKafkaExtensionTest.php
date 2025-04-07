<?php

declare(strict_types=1);

namespace DependencyInjection;

use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\DependencyInjection\RosselKafkaExtension;
use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Rossel\RosselKafka\RosselKafkaBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RosselKafkaExtensionTest extends TestCase
{
    /**
     * Test that the load() method correctly sets the broker_url parameter
     * and loads the services configuration.
     */
    public function testLoadSetsParametersAndLoadsConfiguration(): void
    {
        $container = new ContainerBuilder();
        $extension = new RosselKafkaExtension();

        $configs = [
            [
                RootConfigKeys::BROKER_URL->value => 'kafka://localhost:9092',
            ],
        ];

        $extension->load($configs, $container);

        self::assertTrue($container->hasParameter(RosselKafkaBundle::BUNDLE_NAME.'.'.RootConfigKeys::BROKER_URL->value));
        self::assertSame(
            'kafka://localhost:9092',
            $container->getParameter(RosselKafkaBundle::BUNDLE_NAME.'.'.RootConfigKeys::BROKER_URL->value)
        );
    }

    /**
     * Test that prepend() correctly configures Enqueue if the extension is registered
     * and that it sets the transport broker URL.
     */
    public function testPrependConfiguresEnqueueWhenExtensionExists(): void
    {
        $container = new ContainerBuilder();
        $extension = new RosselKafkaExtension();

        // Simulate the presence of 'enqueue' extension
        $container->registerExtension(new class extends \Symfony\Component\DependencyInjection\Extension\Extension {
            public function load(array $configs, ContainerBuilder $container): void {}
            public function getAlias(): string { return 'enqueue'; }
        });

        $container->prependExtensionConfig(RosselKafkaBundle::BUNDLE_NAME, [
            RootConfigKeys::BROKER_URL->value => 'kafka://broker-prepend:9092',
        ]);

        $extension->prepend($container);

        $enqueueConfig = $container->getExtensionConfig('enqueue');

        self::assertNotEmpty($enqueueConfig);
        $defaultConfig = $enqueueConfig[0]['default'] ?? null;

        self::assertIsArray($defaultConfig);
        self::assertArrayHasKey('transport', $defaultConfig);
        self::assertArrayHasKey('client', $defaultConfig);

        self::assertSame('kafka://broker-prepend:9092', $defaultConfig['transport']);
        self::assertSame('~', $defaultConfig['client']);
    }

    /**
     * Test that prepend() does nothing if the enqueue extension is not registered.
     */
    public function testPrependDoesNothingWhenEnqueueExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        $extension = new RosselKafkaExtension();

        // No enqueue extension registered

        $container->prependExtensionConfig(RosselKafkaBundle::BUNDLE_NAME, [
            RootConfigKeys::BROKER_URL->value => 'kafka://broker-no-enqueue:9092',
        ]);

        // Should not throw any error
        $extension->prepend($container);

        $this->assertEmpty($container->getExtensionConfig('enqueue'));
    }
}
