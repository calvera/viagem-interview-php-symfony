<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OwnerNoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OwnerNoteRepository::class)]
#[ORM\Table(name: 'owner_notes')]
class OwnerNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public Owner $owner,
        #[ORM\Column(type: 'text')]
        public string $note,
        #[ORM\Column(length: 60, nullable: true)]
        public ?string $createdBy = null,
        #[ORM\Column]
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }
}
