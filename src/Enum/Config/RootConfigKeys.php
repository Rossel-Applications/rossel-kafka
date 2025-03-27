<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Enum\Config;

enum RootConfigKeys: string
{
    case ADDRESS = 'address';
    case PORT = 'port';
}
