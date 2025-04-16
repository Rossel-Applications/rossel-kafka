<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Utils;

final readonly class ArrayUtils
{
    public static function pull(array $array, string $key): mixed
    {
        if (!\array_key_exists($key, $array)) {
            throw new \InvalidArgumentException(\sprintf('The key "%s" does not exist.', $key));
        }

        $value = $array[$key];

        array_unshift($array, $value);

        return $value;
    }
}
