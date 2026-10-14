<?php

declare(strict_types=1);

namespace Rossel\RosselKafka;

use Rossel\RosselKafka\Enum\Config\Broker\BrokerConfigKeys;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\Config\Producer\ProducerConfigKeys;
use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Rossel\RosselKafka\Enum\Topic\TopicRole;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class RosselKafkaBundle extends AbstractBundle
{
    public const BUNDLE_NAME = 'rossel_kafka';

    protected string $extensionAlias = self::BUNDLE_NAME;

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $rootNode
            ->children()
            ->arrayNode(RootConfigKeys::BROKER->value)
                ->isRequired()
                ->children()
                ->scalarNode(BrokerConfigKeys::URL->value)
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('The broker url (containing host and port).')
                    ->end()
                ->arrayNode(BrokerConfigKeys::AUTHENTICATION->value)
                    ->addDefaultsIfNotSet()
                    ->children()
                    ->scalarNode(BrokerConfigKeys::SASL_USERNAME->value)
                        ->defaultNull()
                        ->info('SASL username for broker authentication (optional).')
                        ->end()
                    ->scalarNode(BrokerConfigKeys::SASL_PASSWORD->value)
                        ->defaultNull()
                        ->info('SASL password for broker authentication (optional).')
                        ->end()
                    ->scalarNode(BrokerConfigKeys::SASL_MECHANISM->value)
                        ->defaultValue('PLAIN')
                        ->info('SASL mechanism: PLAIN, SCRAM-SHA-256, SCRAM-SHA-512 (default: PLAIN).')
                        ->end()
                    ->scalarNode(BrokerConfigKeys::SSL_CA_CERTIFICATE_URL->value)
                        ->defaultNull()
                        ->info('URL to download the SSL CA certificate from (optional).')
                        ->end()
                    ->scalarNode(BrokerConfigKeys::SSL_CA_CERTIFICATE_PATH->value)
                        ->defaultNull()
                        ->info('Local filesystem path to the SSL CA certificate file (optional, takes priority over url).')
                        ->end()
                    ->scalarNode(BrokerConfigKeys::SSL_CLIENT_CERTIFICATE->value)
                        ->defaultNull()
                        ->info('Client certificate for mTLS: PEM content or local file path (optional).')
                        ->end()
                    ->scalarNode(BrokerConfigKeys::SSL_CLIENT_KEY->value)
                        ->defaultNull()
                        ->info('Client private key for mTLS: PEM content or local file path (optional).')
                        ->end()
                    ->scalarNode(BrokerConfigKeys::SSL_CLIENT_KEY_PASSWORD->value)
                        ->defaultNull()
                        ->info('Password for the client private key (optional).')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode(BrokerConfigKeys::DEBUG->value)
                    ->defaultNull()
                    ->info('Comma-separated list of rdkafka debug contexts (e.g. "broker,topic,msg"). Accepted values: generic, broker, topic, metadata, feature, queue, msg, protocol, cgrp, security, fetch, interceptor, plugin, consumer, admin, eos, mock, assignor, conf, telemetry, all. Disabled by default.')
                    ->end()
                ->append($this->addTopicsNode())
                ->end()
            ->end()
            ->arrayNode(RootConfigKeys::PRODUCER->value)
                ->isRequired()
                ->children()
                ->scalarNode(ProducerConfigKeys::APP_NAME->value)
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('The name of the application, used to identify the app producing the messages.')
                    ->end()
                ->booleanNode(ProducerConfigKeys::SEND_LOG_MESSAGES->value)
                    ->defaultTrue()
                    ->info('Send an EXEC_SUCCESS/EXEC_ERROR log message to the topic after each consumed message (default: true).')
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{
     *   broker: array{
     *     url: string,
     *     debug: string|null,
     *     topics: array<string, array{name: string|null, roles: list<string>}>,
     *     authentication: array<string, string|null>,
     *   },
     *   producer: array{app_name: string, send_log_messages: bool}
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $broker = $config[RootConfigKeys::BROKER->value];
        $producer = $config[RootConfigKeys::PRODUCER->value];
        $topics = $broker[BrokerConfigKeys::TOPICS->value];

        $parameters = [
            'broker.url' => $broker[BrokerConfigKeys::URL->value],
            'broker.debug' => $broker[BrokerConfigKeys::DEBUG->value],
            'broker.topics' => array_map(static fn (array $topic): ?string => $topic[TopicConfigKeys::NAME], $topics),
            'broker.topics_roles' => array_filter(array_map(static fn (array $topic): array => $topic[TopicConfigKeys::ROLES], $topics)),
            'producer.app_name' => $producer[ProducerConfigKeys::APP_NAME->value],
            'producer.send_log_messages' => $producer[ProducerConfigKeys::SEND_LOG_MESSAGES->value],
        ];

        foreach ($broker[BrokerConfigKeys::AUTHENTICATION->value] as $key => $value) {
            $parameters['broker.authentication.'.$key] = $value;
        }

        foreach ($parameters as $name => $value) {
            // Empty literal strings become null; env placeholders are resolved at runtime.
            $container->parameters()->set(self::BUNDLE_NAME.'.'.$name, '' === $value ? null : $value);
        }

        $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()
            ->load(__NAMESPACE__.'\\', '.')
                ->exclude('{Command/Output,Enum,Exception,Model,Resources,Service/Serializer,Utils,RosselKafkaBundle.php}');
    }

    /**
     * Each topic accepts either its name as a plain string, or an array with its name and
     * the roles granted to the application in the topic owner's manifest. Without roles, the
     * topic direction is derived from its name.
     */
    private function addTopicsNode(): NodeDefinition
    {
        $node = (new TreeBuilder(BrokerConfigKeys::TOPICS->value))->getRootNode();
        $children = $node->addDefaultsIfNotSet()->children();

        foreach (TopicConfigKeys::cases() as $topicConfigKey) {
            $children
                ->arrayNode($topicConfigKey->value)
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static fn (string $name): array => [TopicConfigKeys::NAME => $name])
                    ->end()
                    ->beforeNormalization()
                        ->ifNull()
                        ->then(static fn (): array => [TopicConfigKeys::NAME => null])
                    ->end()
                    ->children()
                        ->scalarNode(TopicConfigKeys::NAME)
                            ->defaultNull()
                            ->info('The topic name on the broker.')
                            ->end()
                        ->arrayNode(TopicConfigKeys::ROLES)
                            ->info('Roles granted to the application on this topic (producer, consumer). When empty, the direction is derived from the topic name.')
                            ->enumPrototype()
                                ->values(array_map(static fn (TopicRole $role): string => $role->value, TopicRole::cases()))
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $roles): bool => \count($roles) !== \count(array_unique($roles)))
                                ->thenInvalid('Duplicate roles in %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end();
        }

        return $node;
    }
}
