<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Municipality;
use App\Entity\Owner;
use App\Enum\OwnerType;
use App\Export\OwnerCsv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Cases of legacy tests/Unit/FunctionsTest.php (csv_safe, csv_line). */
final class OwnerCsvTest extends TestCase
{
    #[DataProvider('safeProvider')]
    public function testSafe(string $value, string $expected): void
    {
        self::assertSame($expected, OwnerCsv::safe($value));
    }

    /** @return iterable<string, array{string, string}> */
    public static function safeProvider(): iterable
    {
        yield 'plain text' => ['Novák', 'Novák'];
        yield 'empty' => ['', ''];
        yield 'formula' => ['=HYPERLINK("http://x")', '\'=HYPERLINK("http://x")'];
        yield 'plus' => ['+1+cmd', '\'+1+cmd'];
        yield 'minus' => ['-2+3', '\'-2+3'];
        yield 'at' => ['@SUM(A1)', '\'@SUM(A1)'];
        yield 'tab' => ["\t=1", "'\t=1"];
        yield 'carriage return' => ["\r=1", "'\r=1"];
        yield 'phone' => ['+420 777 123 456', '+420 777 123 456'];
        yield 'phone with brackets' => ['+420 (777) 123-456', '+420 (777) 123-456'];
        yield 'sign inside' => ['Novák-Svoboda', 'Novák-Svoboda'];
    }

    /** @param list<string> $fields */
    #[DataProvider('lineProvider')]
    public function testLine(array $fields, string $expected): void
    {
        $out = fopen('php://memory', 'w+') ?: throw new \RuntimeException('No memory stream.');
        OwnerCsv::line($out, $fields);
        rewind($out);

        self::assertSame($expected, stream_get_contents($out));
    }

    /** @return iterable<string, array{list<string>, string}> */
    public static function lineProvider(): iterable
    {
        yield 'plain' => [['1', 'Novák', ''], "1;Novák;\n"];
        yield 'separator and quote' => [['a;b', 'say "hi"'], "\"a;b\";\"say \"\"hi\"\"\"\n"];
        yield 'quote after backslash' => [['x\\";=2+3;\\"'], "\"x\\\"\";=2+3;\\\"\"\"\n"];
        yield 'empty line' => [[], "\n"];
    }

    public function testRow(): void
    {
        $owner = new Owner(OwnerType::Person, '=Novák', new Municipality('500040', 'Lhota', 'Habřiny', 'Západní kraj'), new \DateTimeImmutable('2012-03-01 08:13:59'));
        $owner->firstName = 'Jan';
        $owner->birthDate = new \DateTimeImmutable('1953-09-06');
        $owner->phone = '+420 777 123 456';
        $owner->duedate = new \DateTimeImmutable('2025-08-19');

        self::assertSame(
            ['', 'Fyzická osoba', '', 'Jan', "'=Novák", '06.09.1953', '', '', '', '', 'Lhota', '+420 777 123 456', '', '19.08.2025', '01.03.2012 08:13'],
            OwnerCsv::row($owner),
        );
    }
}
