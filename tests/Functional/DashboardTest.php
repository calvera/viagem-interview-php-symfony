<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ReportRun;
use App\Tests\Factory\MunicipalityFactory;
use App\Tests\Factory\OwnerFactory;
use App\Tests\Factory\OwnerNoteFactory;
use App\Tests\Factory\OwnershipFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Expectations of legacy admin_dashboard_main.php and tests/Smoke/PagesTest.php. */
final class DashboardTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;
    private string $reportDir;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->reportDir = (string) self::getContainer()->getParameter('app.report_dir');
        new Filesystem()->remove($this->reportDir);
    }

    public function testEmptyDashboard(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Přehled');
        self::assertSame(['0 Vlastníci', '0 Parcely', '0 Vlastnické vztahy', '0 Obce'], $crawler->filter('.card')->each(static fn (Crawler $c): string => $c->text()));
        self::assertSame(['/vlastnici', '/obce'], $crawler->filter('.card a')->each(static fn (Crawler $a): ?string => $a->attr('href')));
        self::assertSelectorTextSame('h2 + .empty', 'Žádné poznámky.');
        self::assertSelectorNotExists('.hint');
        self::assertSelectorTextContains('#main_content > .empty:last-child', 'Report zatím nebyl vygenerován.');
        self::assertLessThanOrEqual(6, MunicipalityListTest::queryCount($crawler));
    }

    public function testCountsLatestNotesRunsAndNewestReport(): void
    {
        MunicipalityFactory::createMany(2);
        OwnershipFactory::createMany(3);
        $owner = OwnerFactory::new()->company('Statek Lhota spol. s r.o.', '01256748')->create();
        OwnerNoteFactory::createMany(10, ['note' => 'starší']);
        OwnerNoteFactory::createOne(['owner' => $owner, 'note' => str_repeat('á', 121), 'createdBy' => 'kancelar', 'createdAt' => new \DateTimeImmutable('2021-05-15 17:00')]);
        $em = self::getContainer()->get('doctrine')->getManager();
        foreach (['2026-11-25' => '2.70', '2026-11-26' => '2.74'] as $date => $duration) {
            $em->persist(new ReportRun(new \DateTimeImmutable($date), 'host', $duration));
        }
        $em->flush();
        new Filesystem()->dumpFile($this->reportDir.'/nightly_2026-11-25.txt', 'starý report');
        new Filesystem()->dumpFile($this->reportDir.'/nightly_2026-11-26.txt', "NOČNÍ REPORT\n<b>x</b>");

        $crawler = $this->client->request('GET', '/');

        self::assertSame(
            ['14', '3', '3', (string) MunicipalityFactory::count()],
            $crawler->filter('.card-value')->each(static fn (Crawler $c): string => str_replace(' ', '', $c->text())),
        );
        $rows = $crawler->filter('tbody tr');
        self::assertCount(10, $rows);
        self::assertSame(['15. 5. 2021 17:00', 'Statek Lhota spol. s r.o.', str_repeat('á', 120).'…', 'kancelar'], $rows->first()->filter('td')->each(static fn (Crawler $td): string => $td->text()));
        self::assertSame('/vlastnici/'.$owner->id, $rows->first()->filter('a')->attr('href'));
        self::assertSelectorTextSame('.hint', 'Poslední běhy: 26. 11. 2026 (2.74 s), 25. 11. 2026 (2.70 s)');
        self::assertSame("NOČNÍ REPORT\n<b>x</b>", $crawler->filter('pre.report')->text(normalizeWhitespace: false));
        self::assertLessThanOrEqual(6, MunicipalityListTest::queryCount($crawler));
    }

    public function testRequestsAreWrittenToAccessLog(): void
    {
        $this->client->request('GET', '/obce?search=x', server: ['REMOTE_ADDR' => '10.0.0.7']);

        $rows = self::getContainer()->get('doctrine.dbal.default_connection')->fetchAllAssociative('SELECT page, ip, user_name FROM access_log');
        self::assertSame([['page' => '/obce?search=x', 'ip' => '10.0.0.7', 'user_name' => 'operator.a']], $rows);
    }
}
