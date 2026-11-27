<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Owner;
use App\Entity\Ownership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ownership>
 */
class OwnershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ownership::class);
    }

    /**
     * Shares of the owner with parcels and municipalities in one query, in the legacy detail order.
     *
     * @return list<Ownership>
     */
    public function forOwner(Owner $owner): array
    {
        return $this->createQueryBuilder('os')
            ->addSelect('p', 'm')
            ->join('os.parcel', 'p')
            ->join('p.municipality', 'm')
            ->where('os.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('m.name')
            ->addOrderBy('p.cadastralArea')
            ->addOrderBy('p.parcelNumber')
            ->addOrderBy('os.id')
            ->getQuery()
            ->getResult();
    }
}
