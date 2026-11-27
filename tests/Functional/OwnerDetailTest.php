<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\MunicipalityFactory;
use App\Tests\Factory\OwnerFactory;
use App\Tests\Factory\OwnerNoteFactory;
use App\Tests\Factory\OwnershipFactory;
use App\Tests\Factory\ParcelFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy tests/Smoke/PagesTest.php and e2e owners.spec.ts / notes.spec.ts. */
final class OwnerDetailTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testPersonDetailWithParcelsAndAreas(): void
    {
        $topolnice = MunicipalityFactory::createOne(['name' => 'Horní Topolnice', 'code' => '652147', 'district' => 'Kostelany']);
        $ves = MunicipalityFactory::createOne(['name' => 'Arnoltov']);
        $owner = OwnerFactory::createOne([
            'firstName' => 'Jana', 'lastName' => 'Svobodová', 'birthDate' => new \DateTimeImmutable('1953-09-06'),
            'street' => 'Lesní 14', 'zip' => '10031', 'city' => 'Horní Topolnice', 'municipality' => $topolnice,
            'createdAt' => new \DateTimeImmutable('2012-03-01 08:13:00'),
        ]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $topolnice, 'cadastralArea' => 'Topolnice', 'cadastralCode' => '616448', 'parcelNumber' => '282', 'areaM2' => 34327, 'landType' => 'orná půda']), 'shareNum' => 1, 'shareDen' => 2, 'lvNumber' => 1]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $ves, 'areaM2' => 9327]), 'shareNum' => 1, 'shareDen' => 0]);
        OwnershipFactory::createOne(['owner' => $owner, 'parcel' => ParcelFactory::new(['municipality' => $topolnice, 'cadastralArea' => 'Topolnice', 'parcelNumber' => '1', 'areaM2' => 1]), 'shareNum' => 3, 'shareDen' => 3]);

        $crawler = $this->get('/vlastnici/'.$owner->id);

        self::assertSelectorTextSame('h1', 'Jana Svobodová');
        self::assertPageTitleSame('Jana Svobodová | Evidence vlastníků');
        self::assertSelectorNotExists('h1 .badge');
        self::assertSame([
            'ID' => (string) $owner->id, 'Typ' => 'Vlastník je fyzická osoba', 'Titul' => '', 'Datum narození' => '6. 9. 1953',
            'Adresa' => 'Lesní 14, 10031 Horní Topolnice', 'Obec (KN)' => 'Horní Topolnice (652147, okres Kostelany)', 'Telefon' => '', 'E-mail' => '',
            'Termín oslovení' => 'nenastaven', 'Založeno' => '1. 3. 2012 08:13', 'Počet parcel' => '3',
            'Výměra celkem' => '4,37 ha', 'Výměra dle podílů' => '1,72 ha', 'Poznámka' => '',
        ], $this->detailRows($crawler));
        self::assertSelectorTextSame('h2', 'Parcely (3)');
        $rows = $crawler->filter('.table-list tbody tr')->each(static fn (Crawler $tr): array => $tr->filter('td')->each(static fn (Crawler $td): string => $td->text()));
        self::assertSame(['Arnoltov', 'Horní Topolnice', 'Horní Topolnice'], array_column($rows, 0));
        self::assertSame(['1', '282'], array_column(\array_slice($rows, 1), 2));
        self::assertSame(['Topolnice (616448)', '282', '1', 'Orná půda', '3,43 ha', '1/2', '1,72 ha'], \array_slice($rows[2], 1));
        self::assertSame(['9 327 m²', '1/0', '–'], \array_slice($rows[0], 5));
        self::assertSame(['celý', '1 m²'], \array_slice($rows[1], 6));
        self::assertSelectorTextSame('#notes', 'Poznámky (0)');
        self::assertSelectorTextSame('#notes + form + .empty', 'Zatím žádné poznámky.');
        self::assertLessThanOrEqual(6, MunicipalityListTest::queryCount($crawler));
    }

    public function testCompanyDetailWithBadgeIcoAndNote(): void
    {
        $owner = OwnerFactory::new()->company('Statek Lhota spol. s r.o.', '01256748')->create([
            'doNotContact' => true, 'note' => "Volat odpoledne\n<b>ne ráno</b>", 'duedate' => new \DateTimeImmutable('2025-08-19'),
        ]);

        $crawler = $this->get('/vlastnici/'.$owner->id);

        self::assertSelectorTextSame('h1', 'Statek Lhota spol. s r.o. neoslovovat');
        self::assertSelectorTextSame('h1 .badge-red', 'neoslovovat');
        $rows = $this->detailRows($crawler);
        self::assertSame('Vlastník je právnická osoba', $rows['Typ']);
        self::assertSame('01256748', $rows['IČO']);
        self::assertArrayNotHasKey('Titul', $rows);
        self::assertArrayNotHasKey('Datum narození', $rows);
        self::assertSame('19. 8. 2025', $rows['Termín oslovení']);
        self::assertStringContainsString('<td>Volat odpoledne<br />'."\n".'&lt;b&gt;ne ráno&lt;/b&gt;</td>', (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextSame('.empty', 'Vlastník nemá v evidenci žádné parcely.');
    }

    public function testPersonWithoutFirstNameShowsOnlyLastName(): void
    {
        $owner = OwnerFactory::createOne(['title' => 'Ing.', 'firstName' => null, 'lastName' => 'Novák']);

        $this->get('/vlastnici/'.$owner->id);

        self::assertSelectorTextSame('h1', 'Novák');
    }

    public function testMissingOwner(): void
    {
        $crawler = $this->get('/vlastnici/999999999');

        self::assertSelectorTextSame('h1', 'Vlastník nenalezen');
        self::assertPageTitleSame('Vlastník nenalezen | Evidence vlastníků');
        self::assertSelectorTextContains('#main_content p', 'Vlastník s ID 999999999 v evidenci není.');
        self::assertSame('/vlastnici', $crawler->selectLink('Zpět na výpis vlastníků')->attr('href'));
        self::assertSelectorTextSame('#menu li.active', 'Vlastníci');
    }

    public function testNotesNewestFirst(): void
    {
        $owner = OwnerFactory::createOne();
        OwnerNoteFactory::createOne(['owner' => $owner, 'note' => 'starší', 'createdAt' => new \DateTimeImmutable('2021-05-15 17:00')]);
        OwnerNoteFactory::createOne(['owner' => $owner, 'note' => 'první', 'createdAt' => new \DateTimeImmutable('2022-01-01 08:00')]);
        OwnerNoteFactory::createOne(['owner' => $owner, 'note' => 'druhá', 'createdAt' => new \DateTimeImmutable('2022-01-01 08:00')]);

        $crawler = $this->get('/vlastnici/'.$owner->id);

        self::assertSelectorTextSame('#notes', 'Poznámky (3)');
        self::assertSame(['druhá', 'první', 'starší'], $crawler->filter('.note .note-text')->each(static fn (Crawler $n): string => $n->text()));
        self::assertSame('operator.a, 15. 5. 2021 17:00 smazat', $crawler->filter('.note .note-head')->last()->text());
        self::assertSame('Opravdu smazat poznámku?', $crawler->filter('.note form')->first()->attr('data-turbo-confirm'));
        self::assertSelectorNotExists('#notes + form + .empty');
    }

    public function testAddNote(): void
    {
        $owner = OwnerFactory::createOne();
        $crawler = $this->get('/vlastnici/'.$owner->id);

        $this->client->submit($crawler->selectButton('Přidat poznámku')->form(['note' => "  Odeslán dopis\ns nabídkou.  "]));

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/vlastnici/'.$owner->id.'#notes');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextSame('.flash-ok', 'Poznámka byla uložena.');
        self::assertSelectorTextSame('#notes', 'Poznámky (1)');
        self::assertStringContainsString('<div class="note-text">Odeslán dopis<br />'."\n".'s nabídkou.</div>', (string) $this->client->getResponse()->getContent());
        self::assertStringStartsWith('operator.a, '.new \DateTimeImmutable()->format('j. n. Y'), $crawler->filter('.note-head')->text());

        $this->get('/vlastnici/'.$owner->id);
        self::assertSelectorNotExists('.flash');
    }

    public function testEmptyNoteAndInvalidTokenAreRejected(): void
    {
        $owner = OwnerFactory::createOne();
        $crawler = $this->get('/vlastnici/'.$owner->id);

        $this->client->submit($crawler->selectButton('Přidat poznámku')->form(['note' => "  \n "]));
        $this->client->followRedirect();
        self::assertSelectorTextSame('.flash-error', 'Poznámka nesmí být prázdná.');

        $this->client->request('POST', '/vlastnici/'.$owner->id.'/poznamky', ['_token' => 'bad', 'note' => 'x']);
        self::assertResponseRedirects('/vlastnici/'.$owner->id.'#notes');
        $this->client->followRedirect();
        self::assertSelectorTextSame('.flash-error', 'Platnost formuláře vypršela, zkuste to prosím znovu.');
        self::assertSelectorTextSame('#notes', 'Poznámky (0)');
    }

    public function testDeleteNote(): void
    {
        $owner = OwnerFactory::createOne();
        OwnerNoteFactory::createOne(['owner' => $owner, 'note' => 'smaž mě']);
        $crawler = $this->get('/vlastnici/'.$owner->id);

        $this->client->submit($crawler->selectButton('smazat')->form());

        self::assertResponseRedirects('/vlastnici/'.$owner->id.'#notes', 303);
        $this->client->followRedirect();
        self::assertSelectorTextSame('.flash-ok', 'Poznámka byla smazána.');
        self::assertSelectorTextSame('#notes', 'Poznámky (0)');
        OwnerNoteFactory::assert()->count(0);
    }

    public function testDeleteOfOtherOwnersNoteFailsAndGetIsNotAllowed(): void
    {
        $owner = OwnerFactory::createOne();
        $other = OwnerNoteFactory::createOne(['note' => 'cizí']);
        $crawler = $this->get('/vlastnici/'.$owner->id);
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/vlastnici/'.$owner->id.'/poznamky/'.$other->id.'/smazat', ['_token' => $token]);
        $this->client->followRedirect();
        self::assertSelectorTextSame('.flash-error', 'Poznámku se nepodařilo smazat.');

        $this->client->request('POST', '/vlastnici/'.$owner->id.'/poznamky/'.$other->id.'/smazat', ['_token' => 'bad']);
        $this->client->followRedirect();
        self::assertSelectorTextSame('.flash-error', 'Platnost formuláře vypršela, zkuste to prosím znovu.');

        $this->client->request('GET', '/vlastnici/'.$owner->id.'/poznamky/'.$other->id.'/smazat');
        self::assertResponseStatusCodeSame(405);
        OwnerNoteFactory::assert()->exists(['note' => 'cizí']);
    }

    private function get(string $url): Crawler
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return array<string, string> label => value of both detail tables */
    private function detailRows(Crawler $crawler): array
    {
        $rows = $crawler->filter('.table-detail tr');

        return array_combine(
            $rows->each(static fn (Crawler $tr): string => $tr->filter('th')->text()),
            $rows->each(static fn (Crawler $tr): string => $tr->filter('td')->text()),
        );
    }
}
