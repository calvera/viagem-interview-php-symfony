<?php

declare(strict_types=1);

namespace App\Doctrine;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

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

    /** Counts from the start of each main request (a long-running kernel serves several). */
    #[AsEventListener(priority: 4096)]
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->count = 0;
        }
    }
}
