<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OwnerType;
use App\Repository\OwnerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OwnerRepository::class)]
#[ORM\Table(name: 'owners')]
#[ORM\Index(name: 'idx_owners_name', columns: ['last_name', 'first_name', 'id'])]
#[ORM\Index(name: 'idx_owners_municipality', columns: ['municipality_id', 'last_name', 'first_name', 'id'])]
#[ORM\Index(name: 'idx_owners_ico', columns: ['ico'])]
class Owner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(length: 30, nullable: true)]
    public ?string $title = null;

    #[ORM\Column(length: 60, nullable: true)]
    public ?string $firstName = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    public ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 10, nullable: true)]
    public ?string $ico = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $street = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $city = null;

    #[ORM\Column(length: 6, nullable: true)]
    public ?string $zip = null;

    #[ORM\Column(length: 20, nullable: true)]
    public ?string $phone = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $email = null;

    #[ORM\Column]
    public bool $doNotContact = false;

    /** Date of the planned contact, null when not set. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    public ?\DateTimeImmutable $duedate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $note = null;

    public function __construct(
        #[ORM\Column(length: 1, enumType: OwnerType::class)]
        public OwnerType $type,
        #[ORM\Column(length: 160)]
        public string $lastName,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public Municipality $municipality,
        #[ORM\Column]
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }

    /** Name in lists: company name, or "title first last" of a person. */
    public function isCompany(): bool
    {
        return $this->type === OwnerType::Company;
    }

    public function displayName(): string
    {
        if ($this->isCompany()) {
            return $this->lastName;
        }

        return trim($this->title.' '.$this->firstName.' '.$this->lastName);
    }

    /** Name on the detail page: a person without a first name is shown by the last name only (no title). */
    public function detailName(): string
    {
        $name = $this->isCompany() ? $this->lastName : $this->firstName;

        return $name ? $this->displayName() : $this->lastName;
    }

    /** The contact date has passed; today is not overdue yet (legacy OPENQUESTIONS.md, question 2). */
    public function isOverdue(\DateTimeImmutable $today = new \DateTimeImmutable('today')): bool
    {
        return $this->duedate !== null && $this->duedate < $today;
    }
}
