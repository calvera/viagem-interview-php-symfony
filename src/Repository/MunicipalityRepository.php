<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\QueryParam;
use App\Dto\ListPage;
use App\Dto\MunicipalityListQuery;
use App\Entity\Municipality;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Municipality>
 */
class MunicipalityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Municipality::class);
    }

    public function paginate(MunicipalityListQuery $query): ListPage
    {
        $qb = $this->createQueryBuilder('m');
        if ($query->district !== '') {
            $qb->andWhere('m.district = :district')->setParameter('district', $query->district);
        }
        if ($query->region !== '') {
            $qb->andWhere('m.region = :region')->setParameter('region', $query->region);
        }
        if ($query->search !== '') {
            $qb->andWhere('m.name LIKE :search OR m.code LIKE :search')->setParameter('search', '%'.$query->search.'%');
        }
        $qb->orderBy('m.'.($query->sort ?: 'name'), QueryParam::direction($query->sort ? $query->dir : 'asc'))->addOrderBy('m.id');

        return ListPage::of($qb, $query->page);
    }

    /**
     * Parcel counts by municipality id in one query; municipalities without parcels are left out.
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
            'SELECT IDENTITY(p.municipality) AS id, COUNT(p.id) AS cnt FROM App\Entity\Parcel p
             WHERE p.municipality IN (:ids) GROUP BY p.municipality',
        )->setParameter('ids', $ids)->getArrayResult();

        return array_column($rows, 'cnt', 'id');
    }

    /** @return array<string, string> select options value => label */
    public function districts(): array
    {
        return $this->distinct('district');
    }

    /** @return array<string, string> select options value => label */
    public function regions(): array
    {
        return $this->distinct('region');
    }

    /** @return array<string, string> */
    private function distinct(string $field): array
    {
        $values = $this->createQueryBuilder('m')->select('DISTINCT m.'.$field)->orderBy('m.'.$field)
            ->getQuery()->getSingleColumnResult();

        return array_combine($values, $values);
    }
}
