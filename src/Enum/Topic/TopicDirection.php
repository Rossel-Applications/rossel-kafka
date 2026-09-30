<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Topic;

use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;

enum TopicDirection
{
    /** Topic can only be consumed (subscribed to). */
    case CONSUME;

    /** Topic can only be produced to (published to). */
    case PRODUCE;

    /** Topic supports both consumption and production. */
    case BOTH;

    /**
     * @param array<array-key, TopicRole> $roles
     */
    public static function fromRoles(array $roles): self
    {
        $canProduce = \in_array(TopicRole::PRODUCER, $roles, true);
        $canConsume = \in_array(TopicRole::CONSUMER, $roles, true);

        return match (true) {
            $canProduce && $canConsume => self::BOTH,
            $canProduce => self::PRODUCE,
            $canConsume => self::CONSUME,
            default => throw new \InvalidArgumentException('At least one topic role is required to resolve a direction.'),
        };
    }

    public static function fromConfigKey(TopicConfigKeys $configKey): self
    {
        $value = strtolower($configKey->value);

        if (str_contains($value, 'inout')) {
            return self::BOTH;
        }

        if (str_contains($value, 'output')) {
            return self::CONSUME;
        }

        if (str_contains($value, 'input')) {
            return self::PRODUCE;
        }

        return self::BOTH;
    }
}
