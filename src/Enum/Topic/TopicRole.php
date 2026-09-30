<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Topic;

/**
 * Access role granted to the application on a topic, as declared in the topic owner's manifest.
 */
enum TopicRole: string
{
    case PRODUCER = 'producer';
    case CONSUMER = 'consumer';
}
