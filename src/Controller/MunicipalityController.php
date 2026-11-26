<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MunicipalityController extends AbstractController
{
    #[Route('/obce', name: 'municipality_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('municipality/list.html.twig');
    }
}
