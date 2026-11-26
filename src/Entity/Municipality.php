<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MunicipalityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MunicipalityRepository::class)]
#[ORM\Table(name: 'municipalities')]
#[ORM\Index(name: 'idx_municipalities_name', columns: ['name'])]
class Municipality
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 6)]
        public string $code,
        #[ORM\Column(length: 120)]
        public string $name,
        #[ORM\Column(length: 80)]
        public string $district,
        #[ORM\Column(length: 80)]
        public string $region,
    ) {
    }
}
