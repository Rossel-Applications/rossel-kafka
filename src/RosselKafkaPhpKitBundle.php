<?php

namespace Rossel\RosselKafkaPhpKit;

use Rossel\RosselKafkaPhpKit\Enum\Config\RootConfigKeys;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class RosselKafkaPhpKitBundle extends AbstractBundle
{
    private const DEFAULT_PORT = 9092;

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode(RootConfigKeys::ADDRESS->value)
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The IP address or hostname to connect to.')
                ->end()
            ->integerNode(RootConfigKeys::PORT->value)
                ->defaultValue(self::DEFAULT_PORT)
                ->cannotBeEmpty()
                ->min(1)
                ->max(65535)
                ->info(sprintf('The port number to connect to (default: %s).', self::DEFAULT_PORT))
                ->end()
            ->end();
    }
}
