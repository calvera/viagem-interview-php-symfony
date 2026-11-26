<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OwnerController extends AbstractController
{
    #[Route('/vlastnici', name: 'owner_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('owner/list.html.twig');
    }
}
