<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\MessageHeaders;

use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;

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
