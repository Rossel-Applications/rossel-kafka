<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config;

enum RootConfigKeys: string
{
    case BROKER_URL = 'broker_url';
    case PRODUCER = 'producer';
}
