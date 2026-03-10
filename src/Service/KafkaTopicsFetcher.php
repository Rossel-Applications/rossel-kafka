<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service;

use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Exception\UnconfiguredTopicException;
use Rossel\RosselKafka\Model\Topic;

class KafkaTopicsFetcher
{
    private const KAFKA_TOPIC_MESSAGES_MAPPINGS = [
        TopicConfigKeys::ACCOUNT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::AUTHENTICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        /*
         * todo: add messages from old topic DEAD_LETTER (not documented in Event Catalog, but mentionned [here](https://rossel-applications.atlassian.net/wiki/spaces/MIT/pages/1196851201/Migration+cluster+Kafka))
         */
        TopicConfigKeys::PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30->name => [
        ],
        TopicConfigKeys::ERP_SUBSCRIPTION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::PUBLIC_INHERITANCE_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::SYNC_B2C_INHERITANCE,
        ],
        TopicConfigKeys::INHERITANCE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::NOTIFICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::PUBLIC_NOTIFICATION_INPUT_V1_JSON_DELETE->name => [
            MessageType::SEND_B2C_EMAIL_NOTIFICATION,
        ],
        TopicConfigKeys::OFFER_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::PUBLIC_OFFER_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::SYNC_B2C_ERP_OFFERS,
        ],
        TopicConfigKeys::PROFILE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::PURCHASE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::PUBLIC_OFFER_INPUT_V1_JSON_DELETE->name => [
            MessageType::REQUEST_SYNC_B2C_ERP_OFFERS,
        ],
        TopicConfigKeys::PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->name => [
            MessageType::CANCEL_B2C_SUBSCRIPTION,
            MessageType::CHANGE_OFFER,
            MessageType::CREATE_OR_UPDATE_MOVING_ADDRESS,
            MessageType::CREATE_OR_UPDATE_SEPA,
            MessageType::CREATE_OR_UPDATE_SUSPENSION,
            MessageType::CREATE_OR_UPDATE_TEMPORARY_DELIVERY_ADDRESS,
            MessageType::CREATE_OR_UPDATE_WALLET,
            MessageType::DELETE_MOVING_ADDRESS,
            MessageType::DELETE_SUSPENSION,
            MessageType::DELETE_TEMPORARY_DELIVERY_ADDRESS,
            MessageType::REQUEST_SYNC_B2C_ERP_SUBSCRIPTION,
            MessageType::UPDATE_INVOICE_ADDRESS,
        ],
        TopicConfigKeys::PUBLIC_SUBSCRIPTION_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::SYNC_B2C_ERP_SUBSCRIBED_SSO,
            MessageType::SYNC_B2C_ERP_SUBSCRIPTION,
            MessageType::SYNC_B2C_ERP_SUBSCRIPTION_PAYMENT_METHODS,
        ],
        TopicConfigKeys::PUBLIC_CONTACT_INPUT_V1_JSON_DELETE->name => [
            MessageType::CREATE_OR_UPDATE_B2C_NON_ERP_SUBSCRIPTION,
            MessageType::CREATE_OR_UPDATE_B2C_PREFERENCES,
            MessageType::CREATE_OR_UPDATE_B2C_PROFILE,
        ],
        TopicConfigKeys::INPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
        TopicConfigKeys::OUTPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->name => [
            MessageType::EXEC_SUCCESS,
            MessageType::EXEC_ERROR,
        ],
    ];

    /**
     * @var array<string, Topic>
     */
    private array $topics = [];

    /**
     * @param array<string, string|null> $topics
     */
    public function __construct(
        array $topics,
    ) {
        $this->initializeTopics($topics);
    }

    public function get(TopicConfigKeys $key): Topic
    {
        return $this->topics[$key->name] ?? throw new UnconfiguredTopicException($key);
    }

    /**
     * @return array<string, Topic>
     */
    public function getAll(): array
    {
        return $this->topics;
    }

    /**
     * @return array<array-key, Topic>
     */
    public function getByMessageType(MessageType $messageType): array
    {
        $results = [];

        foreach ($this->topics as $topic) {
            if ($topic->supportsMessageType($messageType)) {
                $results[] = $topic;
            }
        }

        return $results;
    }

    /**
     * @param array<string, string|null> $topics
     */
    private function initializeTopics(
        array $topics,
    ): void {
        foreach ($topics as $topicConfigKey => $topicName) {
            if (null === $topicName || '' === $topicName) {
                continue;
            }

            $this->initializeTopic($topicConfigKey, $topicName);
        }
    }

    private function initializeTopic(
        string $topicConfigKey,
        string $topicName,
    ): void {
        $validatedTopicConfigKey = TopicConfigKeys::from($topicConfigKey);

        $messages = self::KAFKA_TOPIC_MESSAGES_MAPPINGS[$validatedTopicConfigKey->name] ?? [];

        $this->topics[$validatedTopicConfigKey->name] = new Topic(
            configKey: $validatedTopicConfigKey,
            name: $topicName,
            messageTypes: $messages,
        );
    }
}
