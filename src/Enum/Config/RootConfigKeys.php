<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config;

enum RootConfigKeys: string
{
    case ADDRESS = 'address';
    case PORT = 'port';
}
