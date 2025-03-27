<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Enum\MessageHeaders;

use Rossel\RosselKafkaPhpKit\Enum\Infrastructure\KafkaTopic;

enum MessageType
{
    case SYNC_B2C_ERP_SUBSCRIPTION;

    /**
     * @return array<int, KafkaTopic>
     */
    public function getTopics(): array
    {
        return match ($this) {
            self::SYNC_B2C_ERP_SUBSCRIPTION => [
                KafkaTopic::SYNC_ERP,
            ],
        };
    }
}
