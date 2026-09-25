<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Exception;

use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;

final class UnconfiguredTopicException extends \RuntimeException
{
    private const EXCEPTION_MESSAGE = 'Topic "%s" is not configured. Please set the "%s" environment variable.';

    public function __construct(TopicConfigKeys $key)
    {
        parent::__construct(
            \sprintf(self::EXCEPTION_MESSAGE, $key->value, $key->name),
        );
    }
}
