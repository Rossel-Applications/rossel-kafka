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

    public static function fromConfigKey(TopicConfigKeys $configKey): self
    {
        $name = $configKey->name;

        if (str_contains($name, 'INOUT')) {
            return self::BOTH;
        }

        if (str_contains($name, 'OUTPUT')) {
            return self::CONSUME;
        }

        if (str_contains($name, 'INPUT')) {
            return self::PRODUCE;
        }

        return self::BOTH;
    }
}
