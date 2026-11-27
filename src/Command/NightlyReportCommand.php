<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\QueryCounter;
use App\Entity\ReportRun;
use App\Report\NightlyReportBuilder;
use App\Repository\ReportRunRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Port of legacy cron/nightly_report.php; cron: 15 2 * * * bin/console app:nightly-report --no-debug.
 */
#[AsCommand('app:nightly-report', 'Noční report: statistiky do var/reports, záznam běhu, odeslání na reportovací službu.')]
final class NightlyReportCommand
{
    public function __construct(
        private readonly NightlyReportBuilder $builder,
        private readonly ReportRunRepository $runs,
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly QueryCounter $queryCounter,
        #[Target('cron')] private readonly LoggerInterface $logger,
        #[Autowire('%app.report_dir%')] private readonly string $reportDir,
        #[Autowire('%env(REPORT_PING_URL)%')] private readonly string $pingUrl,
        #[Autowire('%env(REPORT_UPLOAD_URL)%')] private readonly string $uploadUrl,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $start = microtime(true);
        $now = new \DateTimeImmutable();
        $runDate = $now->format('Y-m-d');
        $hostname = (string) gethostname();
        $log = function (string $message) use ($output): void {
            $output->writeln(date('Y-m-d H:i:s').' [nightly] '.$message);
            $this->logger->info('[nightly] '.$message);
        };

        $this->connection->insert('access_log', ['page' => 'cli:app:nightly-report', 'ip' => '127.0.0.1', 'created_at' => $now->format('Y-m-d H:i:s')]);
        $log('Start, server '.$hostname);
        $stats = $this->builder->stats();
        $duration = round(microtime(true) - $start, 2);
        $file = $this->reportDir.'/nightly_'.$runDate.'.txt';
        if (!is_dir($this->reportDir)) {
            mkdir($this->reportDir, 0o775, true);
        }
        if (!file_put_contents($file, $this->builder->render($stats, $hostname, $duration, $now))) {
            $log('CHYBA: report nelze zapsat do '.$file);

            return Command::FAILURE;
        }
        $log('Report uložen: '.$file);

        if ($this->runs->findOneBy(['runDate' => $now->setTime(0, 0)]) !== null) {
            $log('Běh za '.$runDate.' už je v historii zapsaný, záznam přeskočen.');
        } else {
            $this->em->persist(new ReportRun($now->setTime(0, 0), $hostname, (string) $duration, $stats['owners_total'], basename($file), $now));
            $this->em->flush();
        }

        $log($this->ping($runDate, $hostname));
        $log($this->upload($file) ? 'Report odeslán.' : 'Report se nepodařilo odeslat, zůstává jen lokálně.');
        $log('Hotovo za '.round(microtime(true) - $start, 2).' s, SQL dotazů: '.$this->queryCounter->count);

        return Command::SUCCESS;
    }

    private function ping(string $runDate, string $hostname): string
    {
        try {
            $response = $this->httpClient->request('GET', $this->pingUrl, ['query' => ['date' => $runDate, 'host' => $hostname], 'timeout' => 10]);
            if ($response->getStatusCode() !== 200) {
                return 'Reportovací služba nedostupná: HTTP '.$response->getStatusCode();
            }

            return 'Reportovací služba: OK ('.trim($response->getContent()).')';
        } catch (ExceptionInterface $e) {
            return 'Reportovací služba nedostupná: '.$e->getMessage();
        }
    }

    private function upload(string $file): bool
    {
        $form = new FormDataPart(['report' => DataPart::fromPath($file, basename($file), 'text/plain')]);
        try {
            return $this->httpClient->request('POST', $this->uploadUrl, [
                'headers' => $form->getPreparedHeaders()->toArray(),
                'body' => $form->bodyToIterable(),
                'timeout' => 20,
            ])->getStatusCode() === 200;
        } catch (ExceptionInterface) {
            return false;
        }
    }
}
