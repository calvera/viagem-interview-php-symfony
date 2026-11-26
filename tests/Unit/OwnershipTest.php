<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Municipality;
use App\Entity\Owner;
use App\Entity\Ownership;
use App\Entity\Parcel;
use App\Enum\OwnerType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OwnershipTest extends TestCase
{
    #[DataProvider('shareProvider')]
    public function testShare(int $num, int $den, string $label, ?float $area): void
    {
        $ownership = self::ownership(1000, $num, $den);

        self::assertSame($label, $ownership->shareLabel());
        self::assertSame($area, $ownership->shareArea()?->m2);
    }

    /** @return iterable<string, array{int, int, string, ?float}> */
    public static function shareProvider(): iterable
    {
        yield 'whole' => [1, 1, 'celý', 1000.0];
        yield 'whole 2/2' => [2, 2, 'celý', 1000.0];
        yield 'half' => [1, 2, '1/2', 500.0];
        yield 'not reduced' => [2, 4, '2/4', 500.0];
        yield 'third' => [1, 3, '1/3', 1000 / 3];
        yield 'invalid zero denominator' => [0, 0, '0/0', null];
        yield 'invalid denominator only' => [3, 0, '3/0', null];
    }

    private static function ownership(int $areaM2, int $num, int $den): Ownership
    {
        $municipality = new Municipality('500040', 'Lhota', 'Habřiny', 'Západní kraj');
        $owner = new Owner(OwnerType::Person, 'Svobodová', $municipality);
        $parcel = new Parcel($municipality, 'Lhota', '600001', '1517/9', $areaM2);

        return new Ownership($owner, $parcel, $num, $den);
    }
}
