<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\DependencyInjection;

use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Rossel\RosselKafka\RosselKafkaBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    private const DEFAULT_PORT = 9092;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(RosselKafkaBundle::BUNDLE_NAME);

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode(RootConfigKeys::HOST->value)
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The IP address or hostname to connect to.')
                ->end()
            ->scalarNode(RootConfigKeys::PORT->value)
                ->defaultValue(self::DEFAULT_PORT)
                ->info(\sprintf('The port number to connect to (default: %s).', self::DEFAULT_PORT))
                ->end()
            ->end();

        return $treeBuilder;
    }
}
