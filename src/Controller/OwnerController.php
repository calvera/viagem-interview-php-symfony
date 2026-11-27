<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\OwnerListQuery;
use App\Entity\Owner;
use App\Export\OwnerExport;
use App\Entity\OwnerNote;
use App\Entity\Ownership;
use App\Form\OwnerEditData;
use App\Form\OwnerType;
use App\Repository\MunicipalityRepository;
use App\Repository\OwnerNoteRepository;
use App\Repository\OwnerRepository;
use App\Repository\OwnershipRepository;
use App\Value\Area;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class OwnerController extends AbstractController
{
    #[Route('/vlastnici', name: 'owner_list', methods: ['GET'])]
    public function list(
        OwnerRepository $owners,
        MunicipalityRepository $municipalities,
        #[MapQueryString] OwnerListQuery $query = new OwnerListQuery(),
    ): Response {
        $list = $owners->paginate($query);
        if ($query->municipality === '') {
            /** @var list<Owner> $items */
            $items = $list->items;
            $owners->loadMunicipalities($items);
        }
        $ids = $list->ids();

        return $this->render('owner/list.html.twig', [
            'query' => $query,
            'list' => $list,
            'parcel_counts' => $owners->parcelCounts($ids),
            'in_municipality' => $query->hasMunicipalityFilter() ? $owners->inMunicipalityStats($ids, $query) : null,
            'municipality' => $query->municipalityId !== '' ? $municipalities->find((int) $query->municipalityId) : null,
        ]);
    }

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

    #[Route('/vlastnici/{id}', name: 'owner_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, OwnerRepository $owners, OwnershipRepository $ownerships, OwnerNoteRepository $notes): Response
    {
        $owner = $owners->find($id);
        if ($owner === null) {
            return $this->render('owner/not_found.html.twig', ['id' => $id]);
        }
        $shares = $ownerships->forOwner($owner);

        return $this->render('owner/detail.html.twig', [
            'owner' => $owner,
            'ownerships' => $shares,
            'total_area' => new Area(array_sum(array_map(static fn (Ownership $os): int => $os->parcel->areaM2, $shares))),
            'share_area' => new Area(array_sum(array_map(static fn (Ownership $os): float => $os->shareArea()->m2 ?? 0, $shares)))->rounded(),
            'notes' => $notes->forOwner($owner),
        ]);
    }

    #[Route('/vlastnici/{id}/upravit', name: 'owner_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        OwnerRepository $owners,
        EntityManagerInterface $em,
        #[Target('owners')] LoggerInterface $logger,
        #[Autowire('%env(APP_USER)%')] string $user,
    ): Response {
        $owner = $owners->find($id);
        if ($owner === null) {
            return $this->render('owner/not_found.html.twig', ['id' => null]);
        }
        $data = OwnerEditData::fromOwner($owner);
        $form = $this->createForm(OwnerType::class, $data, ['company' => $owner->isCompany()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data->applyTo($owner);
            $em->flush();
            $this->addFlash('ok', 'Údaje vlastníka byly uloženy.');
            $logger->info($user.' upravil vlastníka '.$id);

            return $this->redirectToRoute('owner_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('owner/edit.html.twig', ['owner' => $owner, 'form' => $form, 'errors' => $form->getErrors(true)]);
    }

    #[Route('/vlastnici/{id}/poznamky', name: 'owner_note_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addNote(
        Owner $owner,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire('%env(APP_USER)%')] string $user,
    ): Response {
        if (!$this->isCsrfTokenValid('owner_note', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Platnost formuláře vypršela, zkuste to prosím znovu.');
        } elseif (($text = trim($request->getPayload()->getString('note'))) === '') {
            $this->addFlash('error', 'Poznámka nesmí být prázdná.');
        } else {
            $em->persist(new OwnerNote($owner, $text, $user));
            $em->flush();
            $this->addFlash('ok', 'Poznámka byla uložena.');
        }

        return $this->toNotes($owner);
    }

    #[Route('/vlastnici/{id}/poznamky/{noteId}/smazat', name: 'owner_note_delete', requirements: ['id' => '\d+', 'noteId' => '\d+'], methods: ['POST'])]
    public function deleteNote(Owner $owner, int $noteId, Request $request, OwnerNoteRepository $notes): Response
    {
        if (!$this->isCsrfTokenValid('owner_note', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Platnost formuláře vypršela, zkuste to prosím znovu.');
        } elseif ($notes->deleteOwnerNote($owner, $noteId)) {
            $this->addFlash('ok', 'Poznámka byla smazána.');
        } else {
            $this->addFlash('error', 'Poznámku se nepodařilo smazat.');
        }

        return $this->toNotes($owner);
    }

    private function toNotes(Owner $owner): Response
    {
        return $this->redirectToRoute('owner_detail', ['id' => $owner->id, '_fragment' => 'notes'], Response::HTTP_SEE_OTHER);
    }
}
