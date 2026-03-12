<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Utils\ArrayUtils;

final class ArrayUtilsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Return value
    // -------------------------------------------------------------------------

    #[Test]
    public function pullReturnsValueForExistingKey(): void
    {
        $array = ['foo' => 'bar', 'baz' => 42];

        self::assertSame('bar', ArrayUtils::pull($array, 'foo'));
    }

    #[Test]
    public function pullReturnsNullValue(): void
    {
        $array = ['key' => null];

        self::assertNull(ArrayUtils::pull($array, 'key'));
    }

    #[Test]
    public function pullReturnsIntegerValue(): void
    {
        $array = ['count' => 99];

        self::assertSame(99, ArrayUtils::pull($array, 'count'));
    }

    // -------------------------------------------------------------------------
    // Side effect: key removal
    // -------------------------------------------------------------------------

    #[Test]
    public function pullRemovesKeyFromArray(): void
    {
        $array = ['foo' => 'bar', 'baz' => 42];
        ArrayUtils::pull($array, 'foo');

        self::assertArrayNotHasKey('foo', $array);
    }

    #[Test]
    public function pullPreservesOtherKeys(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        ArrayUtils::pull($array, 'b');

        self::assertArrayHasKey('a', $array);
        self::assertArrayHasKey('c', $array);
        self::assertSame(1, $array['a']);
        self::assertSame(3, $array['c']);
    }

    #[Test]
    public function pullLeavesArrayEmptyWhenOnlyKeyRemoved(): void
    {
        $array = ['only' => 'value'];
        ArrayUtils::pull($array, 'only');

        self::assertEmpty($array);
    }

    #[Test]
    public function sequentialPullsRemoveEachKeyInOrder(): void
    {
        $array = ['x' => 10, 'y' => 20, 'z' => 30];

        $x = ArrayUtils::pull($array, 'x');
        $y = ArrayUtils::pull($array, 'y');

        self::assertSame(10, $x);
        self::assertSame(20, $y);
        self::assertArrayNotHasKey('x', $array);
        self::assertArrayNotHasKey('y', $array);
        self::assertArrayHasKey('z', $array);
    }

    // -------------------------------------------------------------------------
    // Exception
    // -------------------------------------------------------------------------

    #[Test]
    public function pullThrowsForMissingKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The key "missing" does not exist.');

        $array = ['present' => 'value'];
        ArrayUtils::pull($array, 'missing');
    }

    #[Test]
    public function pullThrowsForEmptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $array = [];
        ArrayUtils::pull($array, 'key');
    }

    // -------------------------------------------------------------------------
    // Dataset: value types
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valueTypeProvider(): iterable
    {
        yield 'string' => ['hello'];
        yield 'integer' => [42];
        yield 'float' => [3.14];
        yield 'bool true' => [true];
        yield 'bool false' => [false];
        yield 'null' => [null];
        yield 'array' => [['nested']];
    }

    #[Test]
    #[DataProvider('valueTypeProvider')]
    public function pullHandlesVariousValueTypes(mixed $value): void
    {
        $array = ['key' => $value];
        $result = ArrayUtils::pull($array, 'key');

        self::assertSame($value, $result);
        self::assertArrayNotHasKey('key', $array);
    }
}
