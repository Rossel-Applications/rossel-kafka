<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config;

enum RootConfigKeys: string
{
    case HOST = 'host';
    case PORT = 'port';
}
