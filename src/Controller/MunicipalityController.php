<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MunicipalityListQuery;
use App\Dto\QueryParam;
use App\Repository\MunicipalityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class MunicipalityController extends AbstractController
{
    #[Route('/obce', name: 'municipality_list', methods: ['GET'])]
    public function list(
        MunicipalityRepository $municipalities,
        #[MapQueryString] MunicipalityListQuery $query = new MunicipalityListQuery(),
    ): Response {
        $list = $municipalities->paginate($query);

        return $this->render('municipality/list.html.twig', [
            'query' => $query,
            'list' => $list,
            'parcel_counts' => $municipalities->parcelCounts($list->ids()),
            'districts' => $municipalities->districts(),
            'regions' => $municipalities->regions(),
        ]);
    }

    /** Options of the municipality filter (stimulus-autocomplete), from 2 characters. */
    #[Route('/api/obce', name: 'municipality_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request, MunicipalityRepository $municipalities): Response
    {
        $q = QueryParam::text($request->query->all()['q'] ?? '');

        return $this->render('municipality/_autocomplete.html.twig', [
            'municipalities' => mb_strlen($q) < 2 ? [] : $municipalities->startingWith($q),
        ]);
    }
}
