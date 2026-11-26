<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ParcelListQuery;
use App\Repository\MunicipalityRepository;
use App\Repository\ParcelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class ParcelController extends AbstractController
{
    #[Route('/parcely', name: 'parcel_list', methods: ['GET'])]
    public function list(
        ParcelRepository $parcels,
        MunicipalityRepository $municipalities,
        #[MapQueryString] ParcelListQuery $query = new ParcelListQuery(),
    ): Response {
        $list = $parcels->paginate($query);

        return $this->render('parcel/list.html.twig', [
            'query' => $query,
            'list' => $list,
            'owners' => $parcels->ownersFor($list->ids()),
            'land_types' => $parcels->landTypes(),
            'municipality' => $query->municipalityId !== '' ? $municipalities->find((int) $query->municipalityId) : null,
        ]);
    }
}
