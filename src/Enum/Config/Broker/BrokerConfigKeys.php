<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config\Broker;

enum BrokerConfigKeys: string
{
    case URL = 'url';
    case TOPICS = 'topics';
}
