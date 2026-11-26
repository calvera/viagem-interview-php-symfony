<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'access_log')]
class AccessLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        public string $page,
        #[ORM\Column(length: 45)]
        public string $ip,
        #[ORM\Column(length: 60, nullable: true)]
        public ?string $userName = null,
        #[ORM\Column]
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }
}
