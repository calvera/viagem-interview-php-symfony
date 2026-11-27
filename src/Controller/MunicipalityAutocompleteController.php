<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\QueryParam;
use App\Repository\MunicipalityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MunicipalityAutocompleteController extends AbstractController
{
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
