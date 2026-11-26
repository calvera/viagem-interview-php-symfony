<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\MunicipalityFactory;
use App\Tests\Factory\OwnerFactory;
use App\Tests\Factory\OwnershipFactory;
use App\Tests\Factory\ParcelFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy tests/Smoke/PagesTest.php and e2e owners.spec.ts. */
final class OwnerListTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testRowColumnsBadgesAndGreyRowWithoutParcels(): void
    {
        $dubenka = MunicipalityFactory::createOne(['name' => 'Dolní Dubenka nad Lesem']);
        $company = OwnerFactory::new()->company('Statek Lhota spol. s r.o.', '01256748')->create([
            'municipality' => $dubenka, 'doNotContact' => true, 'note' => 'Volat odpoledne', 'createdAt' => new \DateTimeImmutable('2012-03-01 10:36:24'),
        ]);
        OwnershipFactory::createMany(3, ['owner' => $company]);
        OwnerFactory::createOne(['firstName' => 'Jana', 'lastName' => 'Svobodová', 'birthDate' => new \DateTimeImmutable('1953-09-06')]);

        $crawler = $this->get('/vlastnici');

        self::assertSelectorTextSame('h1', 'Vlastníci pozemků');
        $rows = $crawler->filter('tbody tr');
        self::assertSame(['Statek Lhota spol. s r.o. neoslovovat pozn.', 'Právnická osoba', 'IČO 01256748', 'Dolní Dubenka nad Lesem', '3', 'nenastaven', '1. 3. 2012 10:36'], $rows->eq(0)->filter('td')->each(static fn (Crawler $td): string => $td->text()));
        self::assertSame('Volat odpoledne', $rows->eq(0)->filter('.badge')->eq(1)->attr('title'));
        self::assertNull($rows->eq(0)->attr('class'));
        self::assertSame(['Jana Svobodová', 'Fyzická osoba', '6. 9. 1953'], \array_slice($rows->eq(1)->filter('td')->each(static fn (Crawler $td): string => $td->text()), 0, 3));
        self::assertSame('no_parcels', $rows->eq(1)->attr('class'));
        self::assertSelectorNotExists('th:contains("V obci")');
    }

    public function testDefaultSortByNameThenIdAndPagination(): void
    {
        $ids = array_map(static fn (object $o): ?int => $o->id, OwnerFactory::createMany(51, ['firstName' => 'Marie', 'lastName' => 'Marková']));
        OwnerFactory::createOne(['firstName' => 'Adam', 'lastName' => 'Marková']);

        $crawler = $this->get('/vlastnici?page=2');

        self::assertSelectorTextSame('.section_info', 'Nalezeno 52 vlastníků, strana 2 z 2');
        self::assertSame(['/vlastnici/'.$ids[49], '/vlastnici/'.$ids[50]], $crawler->filter('tbody td:first-child a')->each(static fn (Crawler $a): ?string => $a->attr('href')));
        self::assertSelectorTextSame('.pagination b', '2');
    }

    public function testSearchByNameAndByIco(): void
    {
        OwnerFactory::createOne(['firstName' => 'Jana', 'lastName' => 'Svobodová']);
        OwnerFactory::createOne(['firstName' => 'Svobodová', 'lastName' => 'X']);
        OwnerFactory::new()->company('Statek Lhota spol. s r.o.', '01256748')->create();
        OwnerFactory::new()->company('Firma 01256748', '11111111')->create();

        $this->get('/vlastnici?search=Svobodov%C3%A1');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 2 vlastníků pro hledaný výraz „Svobodová“');

        $crawler = $this->get('/vlastnici?search=%2001256748%20');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 vlastníků');
        self::assertSame('Statek Lhota spol. s r.o.', $crawler->filter('tbody td a')->text());
        self::assertSame('', $crawler->filter('input[name="search"]')->attr('value'));
    }

    public function testFiltersByTypeAndDoNotContact(): void
    {
        OwnerFactory::new()->company('A s.r.o.', '11111111')->create(['doNotContact' => true]);
        OwnerFactory::new()->company('B s.r.o.', '22222222')->create();
        OwnerFactory::createOne(['doNotContact' => true]);

        $crawler = $this->get('/vlastnici?f_type=P');
        self::assertSame(['Právnická osoba', 'Právnická osoba'], $this->column($crawler, 2));
        self::assertSame('P', $crawler->filter('select[name="f_type"] option[selected]')->attr('value'));

        $this->get('/vlastnici?f_type=P&f_dnc=1');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 vlastníků');

        $this->get('/vlastnici?f_dnc=0');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 vlastníků');

        $this->get('/vlastnici?f_type='.rawurlencode("x' or '1'='1"));
        self::assertSelectorTextSame('.section_info', 'Nalezeno 0 vlastníků');
    }

    public function testMunicipalityIdFilterShowsLabelAndInMunicipalityColumn(): void
    {
        $lhota = MunicipalityFactory::createOne(['name' => 'Lhota']);
        $ves = MunicipalityFactory::createOne(['name' => 'Ves']);
        $owner = OwnerFactory::createOne(['municipality' => $lhota]);
        $elsewhere = OwnerFactory::createOne(['municipality' => $lhota]);
        OwnerFactory::createOne(['municipality' => $ves]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $lhota, 'areaM2' => 9999]), 'shareNum' => 1, 'shareDen' => 3]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $lhota, 'areaM2' => 3000]), 'shareNum' => 1, 'shareDen' => 0]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $ves, 'areaM2' => 5000])]);
        OwnershipFactory::createOne(['owner' => $elsewhere, 'parcel' => ParcelFactory::new(['municipality' => $ves])]);

        $crawler = $this->get('/vlastnici?f_municipality_id='.$lhota->id.'&f_type=F');

        self::assertSelectorTextSame('.section_info', 'Nalezeno 2 vlastníků');
        self::assertSelectorTextSame('.hidden_filter', 'Obec: Lhota ×');
        self::assertSame('/vlastnici?f_type=F', $crawler->filter('.hidden_filter a')->attr('href'));
        self::assertSame('', $crawler->filter('input[name="f_municipality"]')->attr('value'));
        self::assertSelectorExists('th:contains("V obci")');
        $byId = array_combine(
            $crawler->filter('tbody td:first-child a')->each(static fn (Crawler $a): string => (string) $a->attr('href')),
            $this->column($crawler, 6),
        );
        self::assertSame('2 (3 333 m²)', $byId['/vlastnici/'.$owner->id]);
        self::assertSame('–', $byId['/vlastnici/'.$elsewhere->id]);
    }

    public function testMunicipalityNameFilterMatchesParcelsByNamePart(): void
    {
        $horni = MunicipalityFactory::createOne(['name' => 'Horní Lhota']);
        $lhota = MunicipalityFactory::createOne(['name' => 'LHOTA']);
        $other = MunicipalityFactory::createOne(['name' => 'Habřiny']);
        $owner = OwnerFactory::createOne(['municipality' => $horni]);
        OwnerFactory::createOne(['municipality' => $other]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $horni, 'areaM2' => 1000]), 'shareNum' => 1, 'shareDen' => 2]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $lhota, 'areaM2' => 300])]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $other, 'areaM2' => 5000])]);

        $crawler = $this->get('/vlastnici?f_municipality=lhota');

        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 vlastníků');
        self::assertSame(['2 (800 m²)'], $this->column($crawler, 6));
        self::assertSame(['Horní Lhota'], $this->column($crawler, 4));
    }

    public function testShortMunicipalityFilterIsIgnoredWithNote(): void
    {
        $lhota = MunicipalityFactory::createOne(['name' => 'Lhota']);
        OwnerFactory::createOne(['municipality' => $lhota]);
        OwnerFactory::createOne();

        foreach (['x' => 'x', '%20%C5%BD%20' => 'Ž'] as $value => $shown) {
            $crawler = $this->get('/vlastnici?f_municipality='.$value);
            self::assertSelectorTextSame('.section_info', 'Nalezeno 2 vlastníků');
            self::assertSame($shown, $crawler->filter('input[name="f_municipality"]')->attr('value'));
            self::assertSelectorTextSame('.filter_note', 'zadejte aspoň 2 znaky');
            self::assertSelectorNotExists('th:contains("V obci")');
        }

        $this->get('/vlastnici?f_municipality=xy');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 0 vlastníků');
        self::assertSelectorNotExists('.filter_note');

        $this->get('/vlastnici?f_municipality_id='.$lhota->id.'&f_municipality=x');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 vlastníků');
        self::assertSelectorExists('th:contains("V obci")');
        self::assertSelectorTextSame('.filter_note', 'zadejte aspoň 2 znaky');
    }

    public function testInvalidMunicipalityIdIsIgnored(): void
    {
        OwnerFactory::createMany(2);

        foreach (['abc', rawurlencode('1 or 1=1'), '-1', '1&f_municipality_id%5B%5D=1'] as $value) {
            $this->get('/vlastnici?f_municipality_id='.$value);
            self::assertSelectorTextSame('.section_info', 'Nalezeno 2 vlastníků');
            self::assertSelectorNotExists('.hidden_filter');
            self::assertSelectorNotExists('th:contains("V obci")');
        }
    }

    public function testDuedateStates(): void
    {
        OwnerFactory::createOne(['lastName' => 'A', 'duedate' => new \DateTimeImmutable('yesterday')]);
        OwnerFactory::createOne(['lastName' => 'B', 'duedate' => new \DateTimeImmutable('today')]);
        OwnerFactory::createOne(['lastName' => 'C', 'duedate' => null]);

        $crawler = $this->get('/vlastnici');

        $cells = $crawler->filter('tbody td:nth-child(6) span');
        self::assertSame(['overdue', 'planned', 'muted'], $cells->each(static fn (Crawler $s): ?string => $s->attr('class')));
        self::assertSame(new \DateTimeImmutable('today')->format('j. n. Y'), $cells->eq(1)->text());
        self::assertSame('nenastaven', $cells->eq(2)->text());
    }

    public function testSortByCreatedAtTogglesArrow(): void
    {
        OwnerFactory::createOne(['firstName' => null, 'lastName' => 'A', 'createdAt' => new \DateTimeImmutable('2020-01-01')]);
        OwnerFactory::createOne(['firstName' => null, 'lastName' => 'B', 'createdAt' => new \DateTimeImmutable('2021-01-01')]);

        $crawler = $this->get('/vlastnici');
        $crawler = $this->client->click($crawler->selectLink('Založeno')->link());
        self::assertSelectorExists('th a:contains("Založeno ▲")');
        $crawler = $this->client->click($crawler->selectLink('Založeno ▲')->link());
        self::assertSelectorExists('th a:contains("Založeno ▼")');
        self::assertSame(['B', 'A'], $crawler->filter('tbody td:first-child a')->each(static fn (Crawler $a): string => $a->text()));
    }

    public function testHintPlaceholderAndQueryCount(): void
    {
        $lhota = MunicipalityFactory::createOne(['name' => 'Lhota']);
        foreach (OwnerFactory::createMany(3, ['municipality' => $lhota]) as $owner) {
            OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $lhota])]);
        }

        foreach (['/vlastnici', '/vlastnici?f_municipality_id='.$lhota->id, '/vlastnici?f_municipality=Lhota'] as $url) {
            $crawler = $this->get($url);
            self::assertLessThanOrEqual(6, MunicipalityListTest::queryCount($crawler), $url);
        }
        self::assertSame('jméno, příjmení nebo IČO', $crawler->filter('input[name="search"]')->attr('placeholder'));
        self::assertSelectorTextContains('.hint', 'Do pole Hledat lze zadat i osmimístné IČO.');
    }

    private function get(string $url): Crawler
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return list<string> */
    private function column(Crawler $crawler, int $n): array
    {
        return $crawler->filter('tbody tr td:nth-child('.$n.')')->each(static fn (Crawler $td): string => $td->text());
    }
}
