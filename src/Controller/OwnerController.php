<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Owner;
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

    #[Route('/vlastnici/{id}', name: 'owner_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(Owner $owner): Response
    {
        return new Response('<h1>'.htmlspecialchars($owner->detailName()).'</h1>');
    }
}
