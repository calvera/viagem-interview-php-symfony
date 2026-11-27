<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Owner;
use App\Tests\Factory\OwnerFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy tests/Smoke/PagesTest.php, e2e owners.spec.ts and owners_edit.php. */
final class OwnerEditTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testPersonFormIsPrefilled(): void
    {
        $owner = OwnerFactory::createOne(['firstName' => 'Jana', 'lastName' => 'Svobodová', 'zip' => '10031', 'duedate' => new \DateTimeImmutable('2025-08-19'), 'doNotContact' => true]);

        $crawler = $this->client->request('GET', '/vlastnici/'.$owner->id);
        $crawler = $this->client->click($crawler->selectLink('Upravit údaje')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Úprava vlastníka');
        self::assertPageTitleSame('Úprava – Jana Svobodová | Evidence vlastníků');
        self::assertSame('/vlastnici/'.$owner->id, $crawler->selectLink('‹ zpět na detail')->attr('href'));
        $values = $this->form($crawler)->getValues();
        self::assertSame(['Jana', 'Svobodová', '10031', '2025-08-19', '1'], [$values['owner[firstName]'], $values['owner[lastName]'], $values['owner[zip]'], $values['owner[duedate]'], $values['owner[doNotContact]']]);
        self::assertSame(['Titul', 'Jméno', 'Příjmení', 'Ulice a č. p.', 'PSČ / město', 'Telefon', 'E-mail', 'Termín oslovení', 'Neoslovovat', 'Poznámka'], $crawler->filter('form th')->each(static fn (Crawler $th): string => $th->text()));
    }

    public function testCompanyFormHasNameAndReadOnlyIco(): void
    {
        $owner = OwnerFactory::new()->company('Statek Lhota spol. s r.o.', '01256748')->create();

        $crawler = $this->get('/vlastnici/'.$owner->id.'/upravit');

        self::assertSame(['Název firmy', 'IČO'], \array_slice($crawler->filter('form th')->each(static fn (Crawler $th): string => $th->text()), 0, 2));
        self::assertSame('Statek Lhota spol. s r.o.', $this->form($crawler)->getValues()['owner[lastName]']);
        self::assertSame('01256748', $crawler->filter('form td')->eq(1)->text());
        self::assertSelectorNotExists('input[name="owner[firstName]"]');
        self::assertSelectorNotExists('input[name="owner[ico]"]');
    }

    public function testSaveNormalizesValuesLogsAndRedirectsToDetail(): void
    {
        $owner = OwnerFactory::createOne(['title' => 'Ing.', 'doNotContact' => true]);
        $crawler = $this->get('/vlastnici/'.$owner->id.'/upravit');

        $form = $this->form($crawler, [
            'owner[title]' => '  ', 'owner[firstName]' => ' Jan ', 'owner[lastName]' => ' Novák ', 'owner[zip]' => ' 100 31 ',
            'owner[city]' => 'Praha', 'owner[email]' => 'jan@example.cz', 'owner[duedate]' => '2027-01-31', 'owner[note]' => " Volat\nodpoledne ",
        ]);
        $form->remove('owner[doNotContact]');
        $this->client->submit($form);

        self::assertResponseRedirects('/vlastnici/'.$owner->id, 303);
        $this->client->followRedirect();
        self::assertSelectorTextSame('.flash-ok', 'Údaje vlastníka byly uloženy.');
        self::assertSelectorTextSame('h1', 'Jan Novák');
        $saved = $this->reload($owner);
        self::assertSame([null, 'Jan', 'Novák', '10031', 'Praha', 'jan@example.cz', '2027-01-31', false, " Volat\nodpoledne "], [
            $saved->title, $saved->firstName, $saved->lastName, $saved->zip, $saved->city, $saved->email, $saved->duedate?->format('Y-m-d'), $saved->doNotContact, $saved->note,
        ]);
        $log = (string) file_get_contents(self::getContainer()->getParameter('kernel.logs_dir').'/owners.log');
        self::assertStringEndsWith('operator.a upravil vlastníka '.$owner->id." [] []\n", $log);
    }

    public function testValidationErrorsInLegacyOrderKeepInput(): void
    {
        $owner = OwnerFactory::createOne(['lastName' => 'Svobodová']);
        $crawler = $this->get('/vlastnici/'.$owner->id.'/upravit');

        $crawler = $this->client->submit($this->form($crawler, [
            'owner[lastName]' => ' ', 'owner[zip]' => '1234', 'owner[email]' => 'neplatny@', 'owner[phone]' => str_repeat('1', 21), 'owner[city]' => 'Brno',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['Vyplňte příjmení.', 'E-mail nemá platný formát.', 'PSČ musí mít 5 číslic.', 'Hodnota smí mít nejvýše 20 znaků.'],
            $crawler->filter('.flash-error')->each(static fn (Crawler $e): string => $e->text()),
        );
        self::assertSame('Brno', $this->form($crawler)->getValues()['owner[city]']);
        self::assertSame('Svobodová', $this->reload($owner)->lastName);
    }

    public function testInvalidDateCompanyNameAndCsrf(): void
    {
        $owner = OwnerFactory::new()->company('Firma s.r.o.', '12345678')->create();
        $this->get('/vlastnici/'.$owner->id.'/upravit');

        $crawler = $this->client->request('POST', '/vlastnici/'.$owner->id.'/upravit', ['owner' => ['lastName' => '', 'duedate' => '31.02.', '_token' => 'bad']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['Platnost formuláře vypršela, odešlete ho prosím znovu.', 'Vyplňte název firmy.', 'Termín oslovení není platné datum.'],
            $crawler->filter('.flash-error')->each(static fn (Crawler $e): string => $e->text()),
        );
        self::assertSame('Firma s.r.o.', $this->reload($owner)->lastName);
    }

    public function testMissingOwner(): void
    {
        $crawler = $this->get('/vlastnici/999999999/upravit');

        self::assertSelectorTextSame('h1', 'Vlastník nenalezen');
        self::assertSelectorTextSame('#main_content p', 'Zpět na výpis vlastníků');
        self::assertSame('/vlastnici', $crawler->selectLink('Zpět na výpis vlastníků')->attr('href'));
    }

    private function get(string $url): Crawler
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /** @param array<string, string> $values */
    private function form(Crawler $crawler, array $values = []): Form
    {
        return $crawler->selectButton('Uložit')->form($values);
    }

    private function reload(Owner $owner): Owner
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->find(Owner::class, $owner->id) ?? throw new \LogicException('Owner is gone.');
    }
}
