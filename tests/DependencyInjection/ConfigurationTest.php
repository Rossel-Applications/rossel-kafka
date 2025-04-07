<?php

namespace DependencyInjection;

use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\DependencyInjection\Configuration;
use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    /**
     * Test that the configuration tree builder creates a valid structure
     * with the required broker_url field.
     */
    public function testGetConfigTreeBuilder(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        self::assertInstanceOf(TreeBuilder::class, $treeBuilder);

        $processor = new Processor();

        // Minimal valid config
        $validConfig = [
            RootConfigKeys::BROKER_URL->value => 'kafka://localhost:9092',
        ];

        $processedConfig = $processor->processConfiguration($configuration, [$validConfig]);

        self::assertArrayHasKey(RootConfigKeys::BROKER_URL->value, $processedConfig);
        self::assertSame('kafka://localhost:9092', $processedConfig[RootConfigKeys::BROKER_URL->value]);
    }

    /**
     * Test that missing broker_url causes a configuration validation error.
     */
    public function testMissingBrokerUrlThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $configuration = new Configuration();
        $processor = new Processor();

        // Missing broker_url
        $invalidConfig = [];

        $processor->processConfiguration($configuration, [$invalidConfig]);
    }
}
