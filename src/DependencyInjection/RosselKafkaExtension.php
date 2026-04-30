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
        /**
         * @var array{
         *   broker: array{
         *     url: string,
         *     topics: array<string, string|null>,
         *     authentication: array{
         *       sasl_username: string|null,
         *       sasl_password: string|null,
         *       sasl_mechanism: string,
         *       ssl_ca_certificate_url: string|null,
         *       ssl_ca_certificate_path: string|null,
         *       ssl_client_certificate: string|null,
         *       ssl_client_key: string|null,
         *       ssl_client_key_password: string|null,
         *     },
         *   },
         *   producer: array{app_name: string}
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        $brokerConfig = $config[RootConfigKeys::BROKER->value];
        $bundleName = RosselKafkaBundle::BUNDLE_NAME;
        $brokerKey = RootConfigKeys::BROKER->value;
        $authKey = BrokerConfigKeys::AUTHENTICATION->value;
        $authConfig = $brokerConfig[$authKey];

        // Normalizes empty strings (from unset env vars) to null for optional parameters.
        $nullable = static fn (?string $v): ?string => (null === $v || '' === $v) ? null : $v;

        $container->setParameter($bundleName.'.'.$brokerKey.'.'.BrokerConfigKeys::URL->value, $brokerConfig[BrokerConfigKeys::URL->value]);
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.BrokerConfigKeys::TOPICS->value, $brokerConfig[BrokerConfigKeys::TOPICS->value]);
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SASL_USERNAME->value, $nullable($authConfig[BrokerConfigKeys::SASL_USERNAME->value]));
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SASL_PASSWORD->value, $nullable($authConfig[BrokerConfigKeys::SASL_PASSWORD->value]));
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SASL_MECHANISM->value, $nullable($authConfig[BrokerConfigKeys::SASL_MECHANISM->value]) ?? 'PLAIN');
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SSL_CA_CERTIFICATE_URL->value, $nullable($authConfig[BrokerConfigKeys::SSL_CA_CERTIFICATE_URL->value]));
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SSL_CA_CERTIFICATE_PATH->value, $nullable($authConfig[BrokerConfigKeys::SSL_CA_CERTIFICATE_PATH->value]));
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SSL_CLIENT_CERTIFICATE->value, $nullable($authConfig[BrokerConfigKeys::SSL_CLIENT_CERTIFICATE->value]));
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SSL_CLIENT_KEY->value, $nullable($authConfig[BrokerConfigKeys::SSL_CLIENT_KEY->value]));
        $container->setParameter($bundleName.'.'.$brokerKey.'.'.$authKey.'.'.BrokerConfigKeys::SSL_CLIENT_KEY_PASSWORD->value, $nullable($authConfig[BrokerConfigKeys::SSL_CLIENT_KEY_PASSWORD->value]));
        $container->setParameter($bundleName.'.'.RootConfigKeys::PRODUCER->value.'.'.ProducerConfigKeys::APP_NAME->value, $config[RootConfigKeys::PRODUCER->value][ProducerConfigKeys::APP_NAME->value]);

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

        $container->prependExtensionConfig('enqueue', [
            'default' => [
                'transport' => $brokerUrl,
                'client' => null,
            ],
        ]);
    }
}
