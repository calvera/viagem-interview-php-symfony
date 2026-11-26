<?php

declare(strict_types=1);

namespace App\Dto;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * One page of a list: 50 rows, a page number beyond the end shows the last page.
 */
final readonly class ListPage
{
    public const int LIMIT = 50;

    /** @param list<object> $items entities with a public $id */
    private function __construct(public array $items, public int $total, public int $page, public int $pages)
    {
    }

    /**
     * Two queries via Doctrine Paginator: COUNT(DISTINCT id) and the page itself
     * (lists join only many-to-one relations, so no subquery is needed).
     */
    public static function of(QueryBuilder $qb, int $page): self
    {
        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $paginator->setUseOutputWalkers(false);
        $total = \count($paginator);
        $pages = (int) ceil($total / self::LIMIT);
        $page = max(1, min($page, $pages));
        $paginator->getQuery()->setFirstResult(($page - 1) * self::LIMIT)->setMaxResults(self::LIMIT);

        return new self($total > 0 ? iterator_to_array($paginator, false) : [], $total, $page, $pages);
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map(intval(...), array_column($this->items, 'id'));
    }
}
