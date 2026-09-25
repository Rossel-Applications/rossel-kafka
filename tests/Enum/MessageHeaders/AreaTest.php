<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Enum\MessageHeaders;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;

final class AreaTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Area}>
     */
    public static function validAreaProvider(): iterable
    {
        yield 'FRANCE' => ['FR', Area::FRANCE];
        yield 'BELGIUM' => ['BE', Area::BELGIUM];
    }

    #[Test]
    #[DataProvider('validAreaProvider')]
    public function fromReturnsCorrectCase(string $value, Area $expected): void
    {
        self::assertSame($expected, Area::from($value));
    }

    #[Test]
    #[DataProvider('validAreaProvider')]
    public function valueMatchesExpectedString(string $value, Area $expected): void
    {
        self::assertSame($value, $expected->value);
    }

    #[Test]
    public function fromInvalidValueThrows(): void
    {
        $this->expectException(\ValueError::class);

        Area::from('XX');
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        /** @var non-empty-string $value */
        $value = 'XX';
        $result = Area::tryFrom($value);
        self::assertNull($result);
    }

    #[Test]
    public function tryFromReturnsEnumForValidValue(): void
    {
        self::assertSame(Area::FRANCE, Area::tryFrom('FR'));
        self::assertSame(Area::BELGIUM, Area::tryFrom('BE'));
    }

    #[Test]
    public function allCasesAreExhaustive(): void
    {
        $cases = Area::cases();

        self::assertCount(2, $cases);

        $names = array_map(fn (Area $a) => $a->name, $cases);
        self::assertContains('FRANCE', $names);
        self::assertContains('BELGIUM', $names);
    }
}
