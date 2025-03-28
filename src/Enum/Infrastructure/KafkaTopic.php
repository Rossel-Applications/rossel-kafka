<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Enum\Infrastructure;

enum KafkaTopic
{
    case SYNC_ERP;
    case CDP;
    case ERP;
    case SYNC_CDP;
}
