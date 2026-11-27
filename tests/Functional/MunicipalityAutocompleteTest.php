<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\MunicipalityFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy tests/Smoke/PagesTest.php (testAutocomplete*) and e2e autocomplete.spec.ts. */
final class MunicipalityAutocompleteTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testPrefixMatchOrderedByNameLimitedTo15(): void
    {
        MunicipalityFactory::createOne(['name' => 'Lhota', 'district' => 'Habřiny']);
        foreach (range('a', 't') as $letter) {
            MunicipalityFactory::createOne(['name' => 'Lhota '.$letter]);
        }
        MunicipalityFactory::createOne(['name' => 'Horní Lhota']);

        $crawler = $this->client->request('GET', '/api/obce?q=%20Lh%20');

        self::assertResponseIsSuccessful();
        $items = $crawler->filter('li[role="option"]');
        self::assertCount(15, $items);
        self::assertSame('Lhota (okres Habřiny)', $items->first()->text());
        self::assertSame(['Lhota', 'Lhota'], [$items->first()->attr('data-autocomplete-value'), $items->first()->attr('data-autocomplete-label')]);
        self::assertSame('Lhota n', $items->last()->attr('data-autocomplete-value'));
    }

    public function testShortArrayAndUnknownQueriesReturnNothing(): void
    {
        MunicipalityFactory::createOne(['name' => 'Čáslav']);
        MunicipalityFactory::createOne(['name' => 'Lhota']);

        foreach (['q=L', 'q='.rawurlencode('Č'), 'q%5B%5D=Lh', '', 'q=xy'] as $query) {
            $this->client->request('GET', '/api/obce?'.$query);
            self::assertResponseIsSuccessful();
            self::assertSame('', trim((string) $this->client->getResponse()->getContent()), $query);
        }
    }

    public function testNameIsEscaped(): void
    {
        MunicipalityFactory::createOne(['name' => 'Lh<img src=x onerror=alert(1)>', 'district' => 'D']);

        $this->client->request('GET', '/api/obce?q=Lh');

        self::assertStringContainsString('>Lh&lt;img src=x onerror=alert(1)&gt; (okres D)</li>', (string) $this->client->getResponse()->getContent());
    }

    public function testListFiltersUseTheAutocomplete(): void
    {
        foreach (['/parcely', '/vlastnici'] as $url) {
            $crawler = $this->client->request('GET', $url);
            $label = $crawler->filter('label[data-controller="autocomplete"]');
            self::assertSame('/api/obce', $label->attr('data-autocomplete-url-value'));
            self::assertSame('2', $label->attr('data-autocomplete-min-length-value'));
            self::assertSame('input', $label->filter('input[name="f_municipality"]')->attr('data-autocomplete-target'));
            self::assertCount(1, $label->filter('ul.autocomplete[data-autocomplete-target="results"]'));
            self::assertSame(['input'], $label->filter('[data-autocomplete-target="input"]')->each(static fn (Crawler $i): string => $i->nodeName()));
        }
    }
}
