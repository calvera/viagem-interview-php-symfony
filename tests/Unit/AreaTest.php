<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Value\Area;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Cases of legacy tests/Unit/FunctionsTest.php (format_area, area_value). */
final class AreaTest extends TestCase
{
    #[DataProvider('formatProvider')]
    public function testFormat(float $m2, string $expected): void
    {
        self::assertSame($expected, (string) new Area($m2));
    }

    /** @return iterable<string, array{float, string}> */
    public static function formatProvider(): iterable
    {
        yield 'zero' => [0, '0 m²'];
        yield 'small' => [512, '512 m²'];
        yield 'below hectare' => [9999, '9 999 m²'];
        yield 'one hectare' => [10000, '1,00 ha'];
        yield 'hectares' => [12345, '1,23 ha'];
        yield 'share sum exactly half, float just below' => [1 / 3 + 1 / 12 + 1 / 12, '1 m²'];
        yield 'literal just below half' => [0.49999999999999994, '1 m²'];
        yield 'two ulps below half' => [0.4999999999999999, '1 m²'];
        yield 'six ulps below 4.5 as on 7.4' => [4.499999999999995, '4 m²'];
        yield '29 ulps below 14.5 as on 7.4' => [14.499999999999948, '15 m²'];
        yield 'half' => [0.5, '1 m²'];
        yield 'below half' => [0.4999, '0 m²'];
        yield 'ten decimal places below half' => [0.4999999996, '0 m²'];
        yield 'float just below 1.5' => [1.4999999999999998, '2 m²'];
        yield 'negative just below half' => [-0.49999999999999994, '-1 m²'];
        yield 'below hectare rounds up' => [9999.5, '10 000 m²'];
        yield 'below hectare rounds down' => [9999.4, '9 999 m²'];
        yield 'hectares half up' => [10050, '1,01 ha'];
        yield 'hectares below half' => [10049, '1,00 ha'];
        yield 'hectares share sum exactly half, float just below' => [47405 * 10 / 12 + 59740 + 8919 + 37422 * 2 / 9 + 11825 * 2 / 12, '11,85 ha'];
    }

    #[DataProvider('roundedProvider')]
    public function testRounded(float $m2, string $expected): void
    {
        self::assertSame($expected, (string) new Area($m2)->rounded());
    }

    /** @return iterable<string, array{float, string}> */
    public static function roundedProvider(): iterable
    {
        yield 'share sum exactly half' => [1 / 3 + 1 / 12 + 1 / 12, '1 m²'];
        yield 'below half' => [0.4999999996, '0 m²'];
        yield 'large share sum exactly half' => [5000049.499999999, '500,01 ha'];
        yield 'larger share sum exactly half' => [20000049.499999996, '2 000,01 ha'];
    }

    public function testPlus(): void
    {
        self::assertSame(1500.5, new Area(1000)->plus(new Area(500.5))->m2);
    }
}
