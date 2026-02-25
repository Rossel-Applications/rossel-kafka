<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\DependencyInjection;

use Rossel\RosselKafka\Consumer\ConsumerInterface;
use Rossel\RosselKafka\Enum\Config\Broker\BrokerConfigKeys;
use Rossel\RosselKafka\Enum\Config\Producer\ProducerConfigKeys;
use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Rossel\RosselKafka\RosselKafkaBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class RosselKafkaExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter(RosselKafkaBundle::BUNDLE_NAME.'.'.RootConfigKeys::BROKER->value.'.'.BrokerConfigKeys::URL->value, $config[RootConfigKeys::BROKER->value][BrokerConfigKeys::URL->value]);
        $container->setParameter(RosselKafkaBundle::BUNDLE_NAME.'.'.RootConfigKeys::BROKER->value.'.'.BrokerConfigKeys::TOPICS->value, $config[RootConfigKeys::BROKER->value][BrokerConfigKeys::TOPICS->value]);
        $container->setParameter(RosselKafkaBundle::BUNDLE_NAME.'.'.RootConfigKeys::PRODUCER->value.'.'.ProducerConfigKeys::APP_NAME->value, $config[RootConfigKeys::PRODUCER->value][ProducerConfigKeys::APP_NAME->value]);

        $container
            ->registerForAutoconfiguration(ConsumerInterface::class)
            ->addTag('rossel_kafka.consumer');

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->preConfigureEnqueue($container);
    }

    /**
     * Set a default configuration for enqueue bundle.
     * The enqueue configuration can be overridden by creating an enqueue configuration file.
     */
    private function preConfigureEnqueue(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('enqueue')) {
            return;
        }

        $baseConfig = $container->getExtensionConfig('enqueue');

        if (!\array_key_exists('default', $baseConfig)) {
            $baseConfig = [
                'default' => [
                ],
            ];
        }

        $brokerUrl = null;

        /** @var array<array-key, mixed> $rosselKafkaConfig */
        $rosselKafkaConfig = $container->getExtensionConfig(RosselKafkaBundle::BUNDLE_NAME);

        foreach ($rosselKafkaConfig as $configParametersGroup) {
            if (\is_array($configParametersGroup)
                && \array_key_exists(RootConfigKeys::BROKER->value, $configParametersGroup)
                && \is_array($configParametersGroup[RootConfigKeys::BROKER->value])
                && \array_key_exists(BrokerConfigKeys::URL->value, $configParametersGroup[RootConfigKeys::BROKER->value])
            ) {
                $brokerUrl = $configParametersGroup[RootConfigKeys::BROKER->value][BrokerConfigKeys::URL->value];
            }
        }

        $baseConfig['default']['transport'] = $brokerUrl;
        $baseConfig['default']['client'] = null;

        $container->prependExtensionConfig('enqueue', $baseConfig);
    }
}
