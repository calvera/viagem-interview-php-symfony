<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\MunicipalityFactory;
use App\Tests\Factory\OwnerFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy tests/Smoke/PagesTest.php (testExport*) and e2e export.spec.ts. */
final class OwnerExportTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const string HEAD = 'ID;Typ;Titul;Jméno;"Příjmení / název";"Datum narození";IČO;Ulice;PSČ;Město;"Obec (KN)";Telefon;E-mail;"Termín oslovení";Založeno';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testExportOfFilteredOwners(): void
    {
        $dubenka = MunicipalityFactory::createOne(['name' => 'Dolní Dubenka nad Lesem']);
        $company = OwnerFactory::new()->company('Statek Lhota spol. s r.o.', '01256748')->create([
            'street' => 'Sadová 157', 'zip' => '10372', 'city' => 'Dolní Dubenka nad Lesem', 'municipality' => $dubenka,
            'phone' => '+420 999 084 132', 'duedate' => new \DateTimeImmutable('2025-06-25'), 'createdAt' => new \DateTimeImmutable('2012-03-01 10:36:24'),
        ]);
        OwnerFactory::createOne();

        $lines = $this->export('/vlastnici/export?search=01256748');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
        self::assertMatchesRegularExpression('/^attachment; filename="vlastnici_\d{8}_\d{4}\.csv"$/', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertMatchesRegularExpression('/^\x{FEFF}"Export vlastníků";"\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}";operator\.a$/u', $lines[0]);
        self::assertSame(self::HEAD, $lines[1]);
        self::assertSame($company->id.';"Právnická osoba";;;"Statek Lhota spol. s r.o.";;01256748;"Sadová 157";10372;"Dolní Dubenka nad Lesem";"Dolní Dubenka nad Lesem";"+420 999 084 132";;25.06.2025;"01.03.2012 10:36"', $lines[2]);
        self::assertSame('', $lines[3]);
        self::assertCount(4, $lines);
        $log = (string) file_get_contents(self::getContainer()->getParameter('kernel.logs_dir').'/export.log');
        self::assertStringEndsWith("operator.a exportoval 1 vlastníků (search=01256748) [] []\n", $log);
    }

    public function testDoNotContactOwnersAreSkippedUnlessAll(): void
    {
        OwnerFactory::new()->company('A s.r.o.', '11111111')->create(['doNotContact' => true]);
        OwnerFactory::new()->company('B s.r.o.', '22222222')->create(['doNotContact' => true]);
        OwnerFactory::new()->company('C s.r.o.', '33333333')->create();
        OwnerFactory::createOne(['doNotContact' => true]);

        $lines = $this->export('/vlastnici/export?f_type=P&f_dnc=1');
        self::assertSame(['', '"Vynecháno 2 vlastníků se zákazem oslovení."', ''], \array_slice($lines, 2));

        $lines = $this->export('/vlastnici/export?f_type=P');
        self::assertCount(6, $lines);
        self::assertStringContainsString('"C s.r.o."', $lines[2]);
        self::assertSame('"Vynecháno 2 vlastníků se zákazem oslovení."', $lines[4]);

        $lines = $this->export('/vlastnici/export?f_type=P&all=1');
        self::assertSame(['"A s.r.o."', '"B s.r.o."', '"C s.r.o."'], array_map(static fn (string $l): string => explode(';', $l)[4], \array_slice($lines, 2, 3)));
        self::assertSame('', $lines[5]);
        self::assertCount(6, $lines);
    }

    public function testExportIgnoresSortAndPage(): void
    {
        OwnerFactory::createOne(['firstName' => null, 'lastName' => 'B', 'createdAt' => new \DateTimeImmutable('2020-01-01')]);
        OwnerFactory::createOne(['firstName' => null, 'lastName' => 'A', 'createdAt' => new \DateTimeImmutable('2021-01-01')]);

        $lines = $this->export('/vlastnici/export?sort=o.created_at&dir=desc&page=2');

        self::assertSame(['A', 'B'], [explode(';', $lines[2])[4], explode(';', $lines[3])[4]]);
    }

    public function testListLinksToExportWithFiltersButNotPageAndSort(): void
    {
        OwnerFactory::createMany(2);

        $crawler = $this->client->request('GET', '/vlastnici?f_type=P&search=x&page=2&sort=o.created_at&dir=desc');

        $link = $crawler->selectLink('Export do CSV');
        self::assertSame('/vlastnici/export?f_type=P&search=x', $link->attr('href'));
        self::assertSame('false', $link->attr('data-turbo'));
    }

    /** @return list<string> */
    private function export(string $url): array
    {
        $this->client->request('GET', $url);

        return explode("\n", $this->client->getInternalResponse()->getContent());
    }
}
