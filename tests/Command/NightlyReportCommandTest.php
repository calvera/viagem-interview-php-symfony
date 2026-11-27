<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\ReportRun;
use App\Tests\Factory\OwnerFactory;
use App\Tests\Factory\OwnerNoteFactory;
use App\Tests\Factory\ParcelFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class NightlyReportCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    /** @var list<array{string, string}> */
    private array $requests = [];

    public function testWritesReportRunAndSendsIt(): void
    {
        $owner = OwnerFactory::createOne(['duedate' => new \DateTimeImmutable('yesterday'), 'doNotContact' => true]);
        OwnerFactory::new()->company('Firma s.r.o.', '12345678')->create(['municipality' => $owner->municipality, 'duedate' => null]);
        ParcelFactory::createOne(['areaM2' => 25000, 'landType' => 'orná půda']);
        OwnerNoteFactory::createOne(['owner' => $owner, 'createdAt' => new \DateTimeImmutable('-1 hour')]);

        $tester = $this->runReport([new MockResponse('pong'), new MockResponse('ok')]);

        $tester->assertCommandIsSuccessful();
        $today = new \DateTimeImmutable('today');
        $file = self::getContainer()->getParameter('app.report_dir').'/nightly_'.$today->format('Y-m-d').'.txt';
        $report = (string) file_get_contents($file);
        self::assertStringStartsWith("NOČNÍ REPORT – Evidence vlastníků\nDatum: ".$today->format('d.m.Y'), $report);
        self::assertStringContainsString("\nVlastníci celkem                                      2\n", $report);
        self::assertStringContainsString("\n  z toho právnické osoby                             1\n", $report);
        self::assertStringContainsString("\nPoznámky celkem / za 24 h                         1 / 1\n", $report);
        self::assertStringContainsString("\nTermín oslovení po splatnosti                        1\n", $report);
        self::assertStringContainsString("\n  Orná půda                                 1 parcel           2,50 ha\n", $report);
        self::assertMatchesRegularExpression('/\n  1\. .{1,45} +2\n/u', $report);
        self::assertStringContainsString('[nightly] Reportovací služba: OK (pong)', $tester->getDisplay());
        self::assertStringContainsString('[nightly] Report odeslán.', $tester->getDisplay());
        self::assertSame('GET', $this->requests[0][0]);
        self::assertStringContainsString('date='.$today->format('Y-m-d'), $this->requests[0][1]);
        self::assertSame('POST', $this->requests[1][0]);
        $run = self::getContainer()->get('doctrine')->getRepository(ReportRun::class)->findOneBy([]);
        self::assertSame([$today->format('Y-m-d'), 2, basename($file)], [$run?->runDate->format('Y-m-d'), $run?->ownersTotal, $run?->reportFile]);
    }

    public function testSecondRunSkipsHistoryAndReportsUnavailableService(): void
    {
        $this->runReport([new MockResponse('pong'), new MockResponse('ok')]);
        $tester = $this->runReport([new MockResponse('', ['http_code' => 503]), new MockResponse('', ['http_code' => 500])]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('už je v historii zapsaný, záznam přeskočen.', $display);
        self::assertStringContainsString('Reportovací služba nedostupná: HTTP 503', $display);
        self::assertStringContainsString('Report se nepodařilo odeslat, zůstává jen lokálně.', $display);
        self::assertMatchesRegularExpression('/Hotovo za [\d.]+ s, SQL dotazů: \d+/', $display);
        self::assertCount(1, self::getContainer()->get('doctrine')->getRepository(ReportRun::class)->findAll());
    }

    /** @param list<MockResponse> $responses */
    private function runReport(array $responses): CommandTester
    {
        $kernel = self::bootKernel();
        new Filesystem()->remove((string) self::getContainer()->getParameter('app.report_dir'));
        self::getContainer()->set('http_client', new MockHttpClient(function (string $method, string $url) use (&$responses): MockResponse {
            $this->requests[] = [$method, $url];

            return array_shift($responses) ?? throw new \LogicException('Unexpected request.');
        }));
        $tester = new CommandTester(new Application($kernel)->find('app:nightly-report'));
        $tester->execute([]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
