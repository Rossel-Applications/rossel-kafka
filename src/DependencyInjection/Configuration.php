<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\DependencyInjection;

use Rossel\RosselKafka\Enum\Config\Producer\ProducerConfigKeys;
use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Rossel\RosselKafka\RosselKafkaBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(RosselKafkaBundle::BUNDLE_NAME);

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode(RootConfigKeys::BROKER_URL->value)
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The broker url (containing host and port).')
                ->end()
            ->arrayNode(RootConfigKeys::PRODUCER->value)
                ->children()
                ->scalarNode(ProducerConfigKeys::APP_NAME->value)
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('The name of the application, used to identify the app producing the messages.')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
