<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\MunicipalityFactory;
use App\Tests\Factory\ParcelFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy tests/Smoke/PagesTest.php and e2e parcels-municipalities.spec.ts. */
final class MunicipalityListTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testDefaultSortByNameThenId(): void
    {
        $second = MunicipalityFactory::createOne(['name' => 'Lhota', 'code' => '500002']);
        MunicipalityFactory::createOne(['name' => 'Borovnice', 'code' => '500003']);
        $first = MunicipalityFactory::createOne(['name' => 'Lhota', 'code' => '500001']);
        MunicipalityFactory::createOne(['name' => 'Čáslav', 'code' => '500004']);

        $crawler = $this->get('/obce');

        self::assertSame(['Borovnice', 'Čáslav', 'Lhota', 'Lhota'], $this->column($crawler, 2));
        self::assertSame(['500003', '500004', $second->code, $first->code], $this->column($crawler, 1));
        self::assertSelectorTextSame('.section_info', 'Nalezeno 4 obcí');
        self::assertSelectorNotExists('.pagination');
    }

    public function testSearchByCodeShowsParcelCountAndLinksById(): void
    {
        $lhota = MunicipalityFactory::createOne(['name' => 'Lhota', 'code' => '500040', 'district' => 'Habřiny', 'region' => 'Západní kraj']);
        MunicipalityFactory::createOne(['name' => 'Ves', 'code' => '600000']);
        ParcelFactory::createMany(3, ['municipality' => $lhota]);

        $crawler = $this->get('/obce?search=500040');

        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 obcí pro hledaný výraz „500040“');
        self::assertSame(['500040', 'Lhota', 'Habřiny', 'Západní kraj', '3', 'vlastníci parcely'], $crawler->filter('tbody tr')->first()->filter('td')->each(static fn (Crawler $td): string => $td->text()));
        self::assertSame('/vlastnici?f_municipality_id='.$lhota->id, $crawler->selectLink('vlastníci')->attr('href'));
        self::assertSame('/parcely?f_municipality_id='.$lhota->id, $crawler->selectLink('parcely')->attr('href'));
    }

    public function testFilterByRegionAndDistrict(): void
    {
        MunicipalityFactory::createOne(['name' => 'A', 'district' => 'Rybnicko', 'region' => 'Západní kraj']);
        MunicipalityFactory::createOne(['name' => 'B', 'district' => 'Habřiny', 'region' => 'Horský kraj']);
        MunicipalityFactory::createOne(['name' => 'C', 'district' => 'Habřiny', 'region' => 'Západní kraj']);

        $crawler = $this->get('/obce?f_region=Z%C3%A1padn%C3%AD%20kraj');
        self::assertSame(['A', 'C'], $this->column($crawler, 2));
        self::assertSame('Západní kraj', $crawler->filter('select[name="f_region"] option[selected]')->text());
        self::assertSame(['– vše –', 'Horský kraj', 'Západní kraj'], $crawler->filter('select[name="f_region"] option')->each(static fn (Crawler $o): string => $o->text()));

        $crawler = $this->get('/obce?f_region=Z%C3%A1padn%C3%AD%20kraj&f_district=Habřiny');
        self::assertSame(['C'], $this->column($crawler, 2));
    }

    public function testSortLinksToggleDirectionAndDropPage(): void
    {
        MunicipalityFactory::createOne(['name' => 'A', 'code' => '100000']);
        MunicipalityFactory::createOne(['name' => 'B', 'code' => '300000']);
        MunicipalityFactory::createOne(['name' => 'C', 'code' => '200000']);

        $crawler = $this->get('/obce?page=1');
        self::assertSame('/obce?sort=code&dir=asc', $crawler->selectLink('Kód')->attr('href'));

        $crawler = $this->get('/obce?sort=code&dir=asc');
        self::assertSame(['100000', '200000', '300000'], $this->column($crawler, 1));
        self::assertSame('/obce?sort=code&dir=desc', $crawler->selectLink('Kód ▲')->attr('href'));

        $crawler = $this->get('/obce?sort=code&dir=desc');
        self::assertSame(['300000', '200000', '100000'], $this->column($crawler, 1));
        self::assertSelectorExists('th a:contains("Kód ▼")');
    }

    public function testPaginationAndHugePageShowsLastPage(): void
    {
        MunicipalityFactory::createMany(51);

        $crawler = $this->get('/obce');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 51 obcí, strana 1 z 2');
        self::assertCount(50, $crawler->filter('tbody tr'));
        self::assertSelectorTextSame('.pagination', '1 2 další ›');

        $crawler = $this->get('/obce?page=99999999999999999999');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 51 obcí, strana 2 z 2');
        self::assertCount(1, $crawler->filter('tbody tr'));
        self::assertSelectorTextSame('.pagination', '‹ předchozí 1 2');
    }

    public function testInvalidParametersAreIgnored(): void
    {
        MunicipalityFactory::createOne(['name' => 'A']);

        $this->get('/obce?sort%5B%5D=x&search%5B%5D=y&page%5B%5D=2&sort=nope');

        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 obcí');
    }

    public function testInjectionFindsNothingAndIsEscaped(): void
    {
        MunicipalityFactory::createOne(['name' => 'A']);

        $this->get('/obce?search='.rawurlencode("zzzqq%' or 'a' like 'a"));
        self::assertSelectorTextContains('.section_info', 'Nalezeno 0 obcí');
        self::assertSelectorTextSame('.empty', 'Nebyly nalezeny žádné obcí.');

        $this->get('/obce?f_district='.rawurlencode("x' or 1=1 or district <> 'y"));
        self::assertSelectorTextContains('.section_info', 'Nalezeno 0 obcí');

        $this->get('/obce?search=%3Cscript%3Ex%3C%2Fscript%3E');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>x</script>', $html);
        self::assertStringContainsString('name="search" value="&lt;script&gt;x&lt;/script&gt;"', $html);
        self::assertStringContainsString('„<b>&lt;script&gt;x&lt;/script&gt;</b>“', $html);
    }

    public function testSearchPlaceholderAndQueryCount(): void
    {
        MunicipalityFactory::createMany(3);

        $crawler = $this->get('/obce');

        self::assertSame('název nebo kód obce', $crawler->filter('input[name="search"]')->attr('placeholder'));
        self::assertLessThanOrEqual(6, self::queryCount($crawler));
    }

    public static function queryCount(Crawler $crawler): int
    {
        self::assertMatchesRegularExpression('/SQL dotazů: \d+/u', $footer = $crawler->filter('#footer')->text());

        return (int) preg_replace('/.*SQL dotazů: (\d+).*/u', '$1', $footer);
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
