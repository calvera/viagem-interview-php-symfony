<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Municipality;
use App\Entity\Owner;
use App\Enum\OwnerType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OwnerTest extends TestCase
{
    #[DataProvider('nameProvider')]
    public function testNames(OwnerType $type, ?string $title, ?string $firstName, string $lastName, string $display, string $detail): void
    {
        $owner = new Owner($type, $lastName, new Municipality('500040', 'Lhota', 'Habřiny', 'Západní kraj'));
        $owner->title = $title;
        $owner->firstName = $firstName;

        self::assertSame($display, $owner->displayName());
        self::assertSame($detail, $owner->detailName());
    }

    /** @return iterable<string, array{OwnerType, ?string, ?string, string, string, string}> */
    public static function nameProvider(): iterable
    {
        yield 'person' => [OwnerType::Person, null, 'Jana', 'Svobodová', 'Jana Svobodová', 'Jana Svobodová'];
        yield 'person with title' => [OwnerType::Person, 'Ing.', 'Markéta', 'Šimková', 'Ing. Markéta Šimková', 'Ing. Markéta Šimková'];
        yield 'person without first name' => [OwnerType::Person, 'Ing.', null, 'Novák', 'Ing.  Novák', 'Novák'];
        yield 'company ignores first name and title' => [OwnerType::Company, 'x', 'y', 'Statek Lhota spol. s r.o.', 'Statek Lhota spol. s r.o.', 'Statek Lhota spol. s r.o.'];
    }

    public function testTypeLabels(): void
    {
        self::assertSame('Fyzická osoba', OwnerType::Person->label());
        self::assertSame('Právnická osoba', OwnerType::Company->label());
    }
}
