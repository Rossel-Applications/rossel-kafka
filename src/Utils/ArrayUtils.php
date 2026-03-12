<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Utils;

final readonly class ArrayUtils
{
    /**
     * @param array<array-key, mixed> $array
     */
    public static function pull(array &$array, string $key): mixed
    {
        if (!\array_key_exists($key, $array)) {
            throw new \InvalidArgumentException(\sprintf('The key "%s" does not exist.', $key));
        }

        $value = $array[$key];

        unset($array[$key]);

        return $value;
    }
}
