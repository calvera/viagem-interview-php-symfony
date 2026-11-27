<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OwnerNoteRepository;
use App\Repository\ReportRunRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(
        Connection $connection,
        OwnerNoteRepository $notes,
        ReportRunRepository $runs,
        #[Autowire('%app.report_dir%')] string $reportDir,
    ): Response {
        return $this->render('dashboard/index.html.twig', [
            'counts' => $connection->fetchAssociative('SELECT (SELECT COUNT(*) FROM owners) AS owners, (SELECT COUNT(*) FROM parcels) AS parcels,
                (SELECT COUNT(*) FROM ownerships) AS ownerships, (SELECT COUNT(*) FROM municipalities) AS municipalities'),
            'notes' => $notes->latest(10),
            'runs' => $runs->findBy([], ['runDate' => 'DESC'], 5),
            'report' => self::latestReport($reportDir),
        ]);
    }

    /** Text of the newest var/reports/nightly_*.txt, null when no report was generated yet. */
    private static function latestReport(string $dir): ?string
    {
        $files = glob($dir.'/nightly_*.txt') ?: [];
        rsort($files);

        return $files === [] ? null : (string) file_get_contents($files[0]);
    }
}
