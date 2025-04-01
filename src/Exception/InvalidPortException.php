<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Exception;

final class InvalidPortException extends \InvalidArgumentException
{
    private const MESSAGE_TEMPLATE = 'Port %s is not valid';

    public function __construct(int|string $port, ?\Throwable $previous = null)
    {
        parent::__construct(
            message: \sprintf(
                self::MESSAGE_TEMPLATE,
                $port,
            ),
            previous: $previous
        );
    }
}
