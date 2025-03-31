<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\MessageHeaders;

enum Area: string
{
    case FRANCE = 'FR';
    case BELGIUM = 'BE';
}
