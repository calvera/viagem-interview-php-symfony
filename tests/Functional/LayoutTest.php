<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Port of legacy e2e/tests/navigation.spec.ts. */
final class LayoutTest extends WebTestCase
{
    use ResetDatabase;

    #[DataProvider('menuProvider')]
    public function testMenuOpensPage(string $url, string $menu, string $heading): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', $heading);
        self::assertPageTitleSame($menu.' | Evidence vlastníků');
        self::assertSelectorTextSame('#menu li.active', $menu);
        self::assertSelectorTextContains('#header .user', 'Přihlášen: operator.a');
        self::assertMatchesRegularExpression('~Evidence vlastníků 3\.0\.0 · vygenerováno za \d+,\d\d s · SQL dotazů: \d+~u', $crawler->filter('#footer')->text());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function menuProvider(): iterable
    {
        yield 'dashboard' => ['/', 'Přehled', 'Přehled'];
        yield 'owners' => ['/vlastnici', 'Vlastníci', 'Vlastníci pozemků'];
        yield 'parcels' => ['/parcely', 'Parcely', 'Parcely'];
        yield 'municipalities' => ['/obce', 'Obce', 'Obce'];
    }

    public function testUnknownPageShows404(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('GET', '/nope');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextSame('h1', 'Stránka nenalezena');
        self::assertPageTitleSame('Stránka nenalezena | Evidence vlastníků');
        $client->clickLink('Zpět na přehled');
        self::assertSelectorTextSame('h1', 'Přehled');
    }
}
