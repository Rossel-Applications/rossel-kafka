<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Enum\Topic\TopicDirection;

final readonly class Topic
{
    public TopicDirection $direction;

    public function __construct(
        private TopicConfigKeys $configKey,
        private string $name,
        /** @var array<array-key, MessageType> */
        private array $messageTypes = [],
    ) {
        $this->direction = TopicDirection::fromConfigKey($configKey);
    }

    public function getConfigKey(): TopicConfigKeys
    {
        return $this->configKey;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return array<array-key, MessageType> */
    public function getMessageTypes(): array
    {
        return $this->messageTypes;
    }

    public function getDirection(): TopicDirection
    {
        return $this->direction;
    }

    public function isConsumable(): bool
    {
        return TopicDirection::CONSUME === $this->direction || TopicDirection::BOTH === $this->direction;
    }

    public function isProducible(): bool
    {
        return TopicDirection::PRODUCE === $this->direction || TopicDirection::BOTH === $this->direction;
    }

    public function supportsMessageType(MessageType $messageType): bool
    {
        return \in_array($messageType, $this->messageTypes, true);
    }
}
