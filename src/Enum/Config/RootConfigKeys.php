<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config;

enum RootConfigKeys: string
{
    case BROKER = 'broker';
    case PRODUCER = 'producer';
}
