<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\OwnerListQuery;
use App\Entity\Owner;
use App\Repository\MunicipalityRepository;
use App\Repository\OwnerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/vlastnici/{id}', name: 'owner_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(Owner $owner): Response
    {
        return new Response('<h1>'.htmlspecialchars($owner->detailName()).'</h1>');
    }
}
