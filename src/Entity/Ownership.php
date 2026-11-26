<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OwnershipRepository;
use App\Value\Area;
use Doctrine\ORM\Mapping as ORM;

/**
 * Share of an owner in a parcel. One owner may hold several shares of the same parcel (different LV).
 */
#[ORM\Entity(repositoryClass: OwnershipRepository::class)]
#[ORM\Table(name: 'ownerships')]
class Ownership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?int $lvNumber = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $createdAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public Owner $owner,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public Parcel $parcel,
        #[ORM\Column]
        public int $shareNum = 1,
        #[ORM\Column]
        public int $shareDen = 1,
    ) {
    }

    /** "celý" for a whole share, otherwise "num/den" (an invalid denominator 0 is never whole). */
    public function shareLabel(): string
    {
        if ($this->shareDen !== 0 && $this->shareNum === $this->shareDen) {
            return 'celý';
        }

        return $this->shareNum.'/'.$this->shareDen;
    }

    /** Area of the share, null for an invalid share with denominator 0. */
    public function shareArea(): ?Area
    {
        if ($this->shareDen === 0) {
            return null;
        }

        return new Area($this->parcel->areaM2 * $this->shareNum / $this->shareDen);
    }
}
