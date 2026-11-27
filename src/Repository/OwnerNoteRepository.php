<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Owner;
use App\Entity\OwnerNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OwnerNote>
 */
class OwnerNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OwnerNote::class);
    }

    /** @return list<OwnerNote> newest first */
    public function forOwner(Owner $owner): array
    {
        return $this->findBy(['owner' => $owner], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** @return list<OwnerNote> newest notes (by id) with their owners */
    public function latest(int $limit): array
    {
        return $this->createQueryBuilder('n')
            ->addSelect('o')
            ->join('n.owner', 'o')
            ->orderBy('n.id', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Deletes the note only when it belongs to the owner; true when a row was deleted. */
    public function deleteOwnerNote(Owner $owner, int $noteId): bool
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.id = :id AND n.owner = :owner')
            ->setParameter('id', $noteId)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->execute() > 0;
    }
}
