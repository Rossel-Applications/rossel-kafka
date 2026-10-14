<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\DependencyInjection;

use Rossel\RosselKafka\Enum\Config\Broker\BrokerConfigKeys;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\Config\Producer\ProducerConfigKeys;
use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Rossel\RosselKafka\Enum\Topic\TopicRole;
use Rossel\RosselKafka\RosselKafkaBundle;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
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
            ->arrayNode(RootConfigKeys::BROKER->value)
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

        return $treeBuilder;
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
