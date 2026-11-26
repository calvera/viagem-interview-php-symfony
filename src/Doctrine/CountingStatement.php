<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class CountingStatement extends AbstractStatementMiddleware
{
    public function __construct(Statement $statement, private readonly QueryCounter $counter)
    {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        $this->counter->increment();

        return parent::execute();
    }
}
