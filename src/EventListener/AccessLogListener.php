<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Writes every page request to access_log after the response is sent (legacy log_access()).
 * Profiler and toolbar requests (/_…) of the dev environment are left out.
 */
#[AsEventListener]
final readonly class AccessLogListener
{
    public function __construct(private Connection $connection, #[Autowire('%env(APP_USER)%')] private string $user)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }
        $this->connection->insert('access_log', [
            'page' => mb_substr($request->getRequestUri(), 0, 255),
            'ip' => $request->getClientIp() ?? '127.0.0.1',
            'user_name' => $this->user,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
