<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Counts every executed query of the default connection in QueryCounter.
 */
#[AsMiddleware(connections: ['default'])]
final readonly class QueryCountingMiddleware implements Middleware
{
    public function __construct(private QueryCounter $counter)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->counter) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly QueryCounter $counter)
            {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                return new CountingConnection(parent::connect($params), $this->counter);
            }
        };
    }
}
