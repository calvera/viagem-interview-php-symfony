<?php

declare(strict_types=1);

namespace App\Doctrine;

/**
 * Number of SQL queries of the current request, shown in the page footer ("SQL dotazů: N").
 */
final class QueryCounter
{
    public private(set) int $count = 0;

    public function increment(): void
    {
        ++$this->count;
    }
}
