<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\DependencyInjection;

use Rossel\RosselKafka\Enum\Config\Broker\BrokerConfigKeys;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
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
                ->arrayNode(BrokerConfigKeys::TOPICS->value)
                    ->addDefaultsIfNotSet()
                    ->children()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_ACCOUNT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_AUTHENTICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_ERP_SUBSCRIPTION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_INHERITANCE_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_INHERITANCE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_NOTIFICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_NOTIFICATION_INPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_OFFER_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_OFFER_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PROFILE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PURCHASE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_OFFER_INPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_SUBSCRIPTION_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_CONTACT_INPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_INPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_OUTPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->scalarNode(TopicConfigKeys::KAFKA_TOPIC_CORE_API_PUBLIC_PROFILE_OUTPUT_V1_JSON_DELETE->value)->defaultNull()->end()
                    ->end()
                    ->end()
                ->end()
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
