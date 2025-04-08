<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Infrastructure;

enum KafkaTopic
{
    case SYNC_ERP;
    case CDP;
    case ERP;
    case SYNC_CDP;

    public static function case(string $case): self
    {
        return match ($case) {
            self::SYNC_ERP->name => self::SYNC_ERP,
            self::CDP->name => self::CDP,
            self::ERP->name => self::ERP,
            self::SYNC_CDP->name => self::SYNC_CDP,
            default => throw new \InvalidArgumentException(sprintf('Unknown kafka topic: %s', $case)),
        };
    }
}
