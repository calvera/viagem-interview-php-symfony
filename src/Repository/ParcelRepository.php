<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\QueryParam;
use App\Dto\ListPage;
use App\Dto\ParcelListQuery;
use App\Entity\Ownership;
use App\Entity\Parcel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parcel>
 */
class ParcelRepository extends ServiceEntityRepository
{
    private const array SORT_FIELDS = ['p.cadastral_area' => 'p.cadastralArea', 'p.area_m2' => 'p.areaM2'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcel::class);
    }

    public function paginate(ParcelListQuery $query): ListPage
    {
        // Left join as in legacy: MariaDB then reads parcels in id order and drops the join from the count.
        $qb = $this->createQueryBuilder('p')->leftJoin('p.municipality', 'm')->addSelect('m');
        if ($query->municipalityId !== '') {
            $qb->andWhere('p.municipality = :municipalityId')->setParameter('municipalityId', (int) $query->municipalityId);
        }
        if ($query->municipality !== '') {
            $qb->andWhere('m.name LIKE :municipality')->setParameter('municipality', '%'.$query->municipality.'%');
        }
        if ($query->landType !== '') {
            $qb->andWhere('p.landType = :landType')->setParameter('landType', $query->landType);
        }
        if ($query->search !== '') {
            $qb->andWhere('p.parcelNumber LIKE :search OR p.cadastralCode LIKE :search')->setParameter('search', '%'.$query->search.'%');
        }
        if ($query->sort !== '') {
            $qb->orderBy(self::SORT_FIELDS[$query->sort], QueryParam::direction($query->dir));
        }
        $qb->addOrderBy('p.id');

        return ListPage::of($qb, $query->page);
    }

    /**
     * Ownerships with owners of the parcels in one query, by parcel id. Owners are ordered by last name,
     * first name and id, ownership id last (one owner may hold two shares of a parcel).
     *
     * @param list<int> $ids
     *
     * @return array<int, list<Ownership>>
     */
    public function ownersFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ownerships = $this->getEntityManager()->createQuery(
            'SELECT os, o FROM App\Entity\Ownership os JOIN os.owner o
             WHERE os.parcel IN (:ids) ORDER BY o.lastName, o.firstName, o.id, os.id',
        )->setParameter('ids', $ids)->getResult();

        $byParcel = [];
        foreach ($ownerships as $ownership) {
            $byParcel[(int) $ownership->parcel->id][] = $ownership;
        }

        return $byParcel;
    }

    /** @return array<string, string> land type => label for the filter ("orná půda" => "Orná půda") */
    public function landTypes(): array
    {
        $types = $this->createQueryBuilder('p')->select('DISTINCT p.landType')->orderBy('p.landType')
            ->getQuery()->getSingleColumnResult();

        return array_combine($types, array_map(static fn (?string $type): string => mb_ucfirst((string) $type), $types));
    }
}
