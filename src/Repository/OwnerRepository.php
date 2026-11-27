<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\ListPage;
use App\Dto\OwnerListQuery;
use App\Dto\QueryParam;
use App\Entity\Municipality;
use App\Entity\Owner;
use App\Value\Area;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Owner>
 */
class OwnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Owner::class);
    }

    public function paginate(OwnerListQuery $query): ListPage
    {
        return ListPage::of($this->listQueryBuilder($query), $query->page);
    }

    /**
     * Filters, search and sort of the owners list, shared with the CSV export.
     * The default sort (last name, first name, id) reads the page from index idx_owners_name.
     */
    public function listQueryBuilder(OwnerListQuery $query, bool $withMunicipality = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o');
        if ($query->municipality !== '' || $withMunicipality) {
            $qb->leftJoin('o.municipality', 'm')->addSelect('m');
        }
        if ($query->municipality !== '') {
            $qb->andWhere('m.name LIKE :municipality')->setParameter('municipality', '%'.$query->municipality.'%');
        }
        if ($query->municipalityId !== '') {
            $qb->andWhere('o.municipality = :municipalityId')->setParameter('municipalityId', (int) $query->municipalityId);
        }
        if ($query->type !== '') {
            $qb->andWhere('o.type = :type')->setParameter('type', $query->type);
        }
        if ($query->dnc !== '') {
            $qb->andWhere('o.doNotContact = :dnc')->setParameter('dnc', $query->dnc);
        }
        if ($query->ico !== '') {
            $qb->andWhere('o.ico = :ico')->setParameter('ico', $query->ico);
        }
        if ($query->search !== '') {
            $qb->andWhere('o.lastName LIKE :search OR o.firstName LIKE :search')->setParameter('search', '%'.$query->search.'%');
        }
        if ($query->sort !== '') {
            return $qb->orderBy('o.createdAt', QueryParam::direction($query->dir))->addOrderBy('o.id');
        }

        // With a search text MariaDB cannot estimate LIKE '%…%' and would walk the whole name index for a rare
        // name; "o.id + 0" gives the same order but not from the index, so it filters first and sorts (as legacy).
        return $qb->orderBy('o.lastName')->addOrderBy('o.firstName')->addOrderBy($query->search !== '' ? 'o.id + 0' : 'o.id');
    }

    /**
     * Owners of the export with their municipality, read one by one; without "all" the do-not-contact owners
     * are left out in SQL, so the limit counts only exported rows (as legacy).
     *
     * @return iterable<Owner>
     */
    public function exportRows(OwnerListQuery $query, int $limit): iterable
    {
        $qb = $this->listQueryBuilder($query, withMunicipality: true)->setMaxResults($limit);
        if (!$query->all) {
            $qb->andWhere('o.doNotContact = false');
        }

        return $qb->getQuery()->toIterable();
    }

    /** Number of do-not-contact owners matching the list filters. */
    public function countDoNotContact(OwnerListQuery $query): int
    {
        return (int) $this->listQueryBuilder($query)
            ->resetDQLPart('orderBy')
            ->select('COUNT(o.id)')
            ->andWhere('o.doNotContact = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Loads the municipalities of the owners in one query (their lazy references get initialized).
     *
     * @param list<Owner> $owners
     */
    public function loadMunicipalities(array $owners): void
    {
        $ids = array_unique(array_map(static fn (Owner $o): ?int => $o->municipality->id, $owners));
        if ($ids === []) {
            return;
        }
        $this->getEntityManager()->createQuery('SELECT m FROM '.Municipality::class.' m WHERE m.id IN (:ids)')
            ->setParameter('ids', array_values($ids))->getResult();
    }

    /**
     * Parcel (ownership) counts by owner id in one query; owners without parcels are left out.
     *
     * @param list<int> $ids
     *
     * @return array<int, int>
     */
    public function parcelCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(os.owner) AS id, COUNT(os.id) AS cnt FROM App\Entity\Ownership os
             WHERE os.owner IN (:ids) GROUP BY os.owner',
        )->setParameter('ids', $ids)->getArrayResult();

        return array_map(intval(...), array_column($rows, 'cnt', 'id'));
    }

    /**
     * "V obci": count and share area of the owners' parcels in the filtered municipality, by owner id;
     * owners without such a parcel are left out. By id in SQL, by a part of the name in PHP (case-insensitive
     * substring of the parcel's municipality name), as legacy.
     *
     * @param list<int> $ids
     *
     * @return array<int, array{count: int, area: Area}>
     */
    public function inMunicipalityStats(array $ids, OwnerListQuery $query): array
    {
        if ($ids === []) {
            return [];
        }

        return $query->municipalityId !== ''
            ? $this->statsByMunicipalityId($ids, (int) $query->municipalityId)
            : $this->statsByMunicipalityName($ids, $query->municipality);
    }

    /**
     * 1e0 makes the division double as in PHP; a share with denominator 0 adds no area.
     *
     * @param list<int> $ids
     *
     * @return array<int, array{count: int, area: Area}>
     */
    private function statsByMunicipalityId(array $ids, int $municipalityId): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT os.owner_id, COUNT(*) AS cnt, SUM(1e0 * p.area_m2 * os.share_num / NULLIF(os.share_den, 0)) AS area
             FROM ownerships os JOIN parcels p ON p.id = os.parcel_id
             WHERE os.owner_id IN (?) AND p.municipality_id = ? GROUP BY os.owner_id',
            [$ids, $municipalityId],
            [ArrayParameterType::INTEGER],
        );

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['owner_id']] = ['count' => (int) $row['cnt'], 'area' => new Area((float) $row['area'])];
        }

        return $stats;
    }

    /**
     * Parcels in the legacy order (municipality, cadastral area, parcel number), so the float sum is the same.
     *
     * @param list<int> $ids
     *
     * @return array<int, array{count: int, area: Area}>
     */
    private function statsByMunicipalityName(array $ids, string $name): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT os.owner_id, os.share_num, os.share_den, p.area_m2, m.name
             FROM ownerships os JOIN parcels p ON p.id = os.parcel_id JOIN municipalities m ON m.id = p.municipality_id
             WHERE os.owner_id IN (?) ORDER BY os.owner_id, m.name, p.cadastral_area, p.parcel_number, os.id',
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        $stats = [];
        foreach ($rows as $row) {
            if (mb_stripos($row['name'], $name) === false) {
                continue;
            }
            $id = (int) $row['owner_id'];
            $area = $row['share_den'] ? $row['area_m2'] * $row['share_num'] / $row['share_den'] : 0;
            $stats[$id] ??= ['count' => 0, 'area' => new Area(0)];
            $stats[$id] = ['count' => $stats[$id]['count'] + 1, 'area' => $stats[$id]['area']->plus(new Area($area))];
        }

        return $stats;
    }
}
