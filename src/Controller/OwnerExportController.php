<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\OwnerListQuery;
use App\Export\OwnerExport;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/** CSV export of the filtered owners list. */
final class OwnerExportController extends AbstractController
{
    #[Route('/vlastnici/export', name: 'owner_export', methods: ['GET'])]
    public function export(
        Request $request,
        OwnerExport $export,
        #[Target('export')] LoggerInterface $logger,
        #[Autowire('%env(APP_USER)%')] string $user,
        #[MapQueryString] OwnerListQuery $query = new OwnerListQuery(),
    ): StreamedResponse {
        return new StreamedResponse(static function () use ($request, $export, $logger, $user, $query): void {
            $out = fopen('php://output', 'w') ?: throw new \RuntimeException('Cannot open the output.');
            $exported = $export->write($out, $query, $user);
            fclose($out);
            $logger->info($user.' exportoval '.$exported.' vlastníků ('.$request->server->getString('QUERY_STRING').')');
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="vlastnici_'.date('Ymd_Hi').'.csv"',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
