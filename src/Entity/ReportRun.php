<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReportRunRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRunRepository::class)]
#[ORM\Table(name: 'report_runs')]
#[ORM\UniqueConstraint(name: 'uniq_report_runs_date', columns: ['run_date'])]
class ReportRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'date_immutable')]
        public \DateTimeImmutable $runDate,
        #[ORM\Column(length: 120, nullable: true)]
        public ?string $hostname = null,
        /** Duration in seconds, e.g. "2.74". */
        #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
        public ?string $duration = null,
        #[ORM\Column(nullable: true)]
        public ?int $ownersTotal = null,
        #[ORM\Column(length: 255, nullable: true)]
        public ?string $reportFile = null,
        #[ORM\Column(nullable: true)]
        public ?\DateTimeImmutable $createdAt = null,
    ) {
    }
}
