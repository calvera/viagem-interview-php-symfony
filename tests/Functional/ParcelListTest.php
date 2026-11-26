<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\OwnerType;
use App\Tests\Factory\MunicipalityFactory;
use App\Tests\Factory\OwnerFactory;
use App\Tests\Factory\OwnershipFactory;
use App\Tests\Factory\ParcelFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy tests/Smoke/PagesTest.php and e2e parcels-municipalities.spec.ts. */
final class ParcelListTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testRowWithOwnersSharesAndLvInOwnerOrder(): void
    {
        $lhota = MunicipalityFactory::createOne(['name' => 'Velká Lhota na Rovni']);
        $parcel = ParcelFactory::createOne(['municipality' => $lhota, 'cadastralArea' => 'Velká Lhota na Rovni', 'parcelNumber' => '106', 'areaM2' => 28935, 'landType' => 'lesní pozemek']);
        $zeman = OwnerFactory::createOne(['firstName' => 'Martina', 'lastName' => 'Zemanová', 'title' => 'MUDr.']);
        $novak1 = OwnerFactory::createOne(['firstName' => 'Václav', 'lastName' => 'Kratochvíl']);
        $novak2 = OwnerFactory::createOne(['firstName' => 'Václav', 'lastName' => 'Kratochvíl']);
        $firm = OwnerFactory::new()->company('Pastviny Lipnice s.r.o.', '12345678')->create();
        OwnershipFactory::createOne(['owner' => $zeman, 'parcel' => $parcel, 'lvNumber' => 2423]);
        OwnershipFactory::createOne(['owner' => $novak2, 'parcel' => $parcel, 'shareNum' => 1, 'shareDen' => 2, 'lvNumber' => 2209]);
        OwnershipFactory::createOne(['owner' => $novak1, 'parcel' => $parcel, 'shareNum' => 1, 'shareDen' => 3, 'lvNumber' => 2209]);
        OwnershipFactory::createOne(['owner' => $firm, 'parcel' => $parcel, 'shareNum' => 1, 'shareDen' => 6, 'lvNumber' => 2209]);

        $crawler = $this->get('/parcely');

        $cells = $crawler->filter('tbody tr td')->each(static fn (Crawler $td): string => $td->text());
        self::assertSame(['Velká Lhota na Rovni', 'Velká Lhota na Rovni', '106', '2209, 2423', 'Lesní pozemek', '2,89 ha'], \array_slice($cells, 0, 6));
        self::assertSame(
            ['/vlastnici/'.$novak1->id, '/vlastnici/'.$novak2->id, '/vlastnici/'.$firm->id, '/vlastnici/'.$zeman->id],
            $crawler->filter('tbody td:nth-child(7) a')->each(static fn (Crawler $a): ?string => $a->attr('href')),
        );
        self::assertSame('Václav Kratochvíl (1/3)Václav Kratochvíl (1/2)Pastviny Lipnice s.r.o. (1/6)MUDr. Martina Zemanová (celý)', $cells[6]);
    }

    public function testParcelWithoutOwnerAndDefaultSortById(): void
    {
        ParcelFactory::createOne(['parcelNumber' => '8', 'areaM2' => 8069, 'cadastralArea' => 'B']);
        $second = ParcelFactory::createOne(['cadastralArea' => 'A']);

        $crawler = $this->get('/parcely');

        self::assertSame(['8', $second->parcelNumber], $this->column($crawler, 3));
        $first = $crawler->filter('tbody tr')->first()->filter('td');
        self::assertSame('', $first->eq(3)->text());
        self::assertSame('8 069 m²', $first->eq(5)->text());
        self::assertSame('bez vlastníka', $first->eq(6)->filter('.muted')->text());
    }

    public function testFilterByMunicipalityIdKeepsOtherFilters(): void
    {
        $lhota = MunicipalityFactory::createOne(['name' => 'Lhota']);
        ParcelFactory::createMany(2, ['municipality' => $lhota, 'landType' => 'orná půda']);
        ParcelFactory::createOne(['municipality' => $lhota, 'landType' => 'zahrada']);
        ParcelFactory::createOne(['landType' => 'orná půda']);

        $crawler = $this->get('/parcely?f_municipality_id='.$lhota->id.'&f_land_type=orn%C3%A1%20p%C5%AFda');

        self::assertSelectorTextSame('.section_info', 'Nalezeno 2 parcel');
        self::assertSelectorTextSame('.hidden_filter', 'Obec: Lhota ×');
        self::assertSame('/parcely?f_land_type=orn%C3%A1%20p%C5%AFda', $crawler->filter('.hidden_filter a')->attr('href'));
        self::assertSame((string) $lhota->id, $crawler->filter('input[type="hidden"][name="f_municipality_id"]')->attr('value'));
        self::assertSame('', $crawler->filter('input[name="f_municipality"]')->attr('value'));
        self::assertSame(['Lhota', 'Lhota'], $this->column($crawler, 1));
        self::assertSame(['Orná půda', 'Orná půda'], $this->column($crawler, 5));
        self::assertSame(['– vše –', 'Orná půda', 'Zahrada'], $crawler->filter('select[name="f_land_type"] option')->each(static fn (Crawler $o): string => $o->text()));
    }

    public function testNonexistentAndInvalidMunicipalityId(): void
    {
        ParcelFactory::createOne();

        $this->get('/parcely?f_municipality_id=999999');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 0 parcel');
        self::assertSelectorTextSame('.hidden_filter', 'Obec: 999999 ×');

        foreach (['abc', rawurlencode('1 or 1=1'), '-1', '1&f_municipality_id%5B%5D=1'] as $value) {
            $this->get('/parcely?f_municipality_id='.$value);
            self::assertSelectorTextSame('.section_info', 'Nalezeno 1 parcel');
            self::assertSelectorNotExists('.hidden_filter');
        }
    }

    public function testFilterByMunicipalityNameAndSearch(): void
    {
        $horni = MunicipalityFactory::createOne(['name' => 'Horní Lhota']);
        ParcelFactory::createOne(['municipality' => $horni, 'parcelNumber' => 'st. 121']);
        ParcelFactory::createOne(['municipality' => $horni, 'parcelNumber' => '1517/9', 'cadastralCode' => '700001']);
        ParcelFactory::createOne(['parcelNumber' => 'st. 121']);

        $this->get('/parcely?f_municipality=lhota');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 2 parcel');

        $this->get('/parcely?f_municipality=lhota&search=st.%20121');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 parcel pro hledaný výraz „st. 121“');

        $this->get('/parcely?search=700001');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 1 parcel pro hledaný výraz „700001“');

        $this->get('/parcely?f_municipality='.rawurlencode("zzzqq%' or 'a' like 'a"));
        self::assertSelectorTextSame('.section_info', 'Nalezeno 0 parcel');
        $this->get('/parcely?search=x%27');
        self::assertSelectorTextSame('.section_info', 'Nalezeno 0 parcel pro hledaný výraz „x'."'".'“');
    }

    public function testSortByAreaAndEscapedFilter(): void
    {
        ParcelFactory::createOne(['areaM2' => 500]);
        ParcelFactory::createOne(['areaM2' => 20000]);
        ParcelFactory::createOne(['areaM2' => 1500]);

        $crawler = $this->get('/parcely?sort=p.area_m2&dir=desc');
        self::assertSame(['2,00 ha', '1 500 m²', '500 m²'], $this->column($crawler, 6));
        self::assertSelectorExists('th a:contains("Výměra ▼")');

        $this->get('/parcely?f_municipality=%22%3E%3Cscript%3Ex%3C%2Fscript%3E');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>x</script>', $html);
        self::assertStringContainsString('name="f_municipality" value="&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $html);
    }

    public function testPlaceholderHintAndQueryCount(): void
    {
        $parcel = ParcelFactory::createOne();
        OwnershipFactory::createOne(['parcel' => $parcel, 'owner' => OwnerFactory::new(['type' => OwnerType::Person])]);

        $crawler = $this->get('/parcely?f_municipality_id='.$parcel->municipality->id);

        self::assertSame('číslo parcely nebo kód k. ú.', $crawler->filter('input[name="search"]')->attr('placeholder'));
        self::assertSelectorTextContains('.hint', 'Hledat lze podle čísla parcely');
        self::assertLessThanOrEqual(6, MunicipalityListTest::queryCount($crawler));
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
