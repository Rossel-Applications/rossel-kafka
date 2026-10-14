<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config\Producer;

enum ProducerConfigKeys: string
{
    case APP_NAME = 'app_name';
    case SEND_LOG_MESSAGES = 'send_log_messages';
}
