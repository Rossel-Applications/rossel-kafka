<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

final readonly class Topic
{
    public function __construct(
        private TopicConfigKeys $configKey,
        private string $name,
        /** @var array<array-key, MessageType> */
        private array $messageTypes = [],
    ) {
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

    public function supportsMessageType(MessageType $messageType): bool
    {
        return \in_array($messageType, $this->messageTypes, true);
    }
}
