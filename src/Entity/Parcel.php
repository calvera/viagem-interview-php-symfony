<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ParcelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParcelRepository::class)]
#[ORM\Table(name: 'parcels')]
#[ORM\Index(name: 'idx_parcels_land_type', columns: ['land_type'])]
class Parcel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    /** Land type in lower case as in the cadastre ("orná půda"). */
    #[ORM\Column(length: 60, nullable: true)]
    public ?string $landType = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public Municipality $municipality,
        #[ORM\Column(length: 160)]
        public string $cadastralArea,
        #[ORM\Column(length: 6)]
        public string $cadastralCode,
        #[ORM\Column(length: 20)]
        public string $parcelNumber,
        #[ORM\Column]
        public int $areaM2,
    ) {
    }
}
