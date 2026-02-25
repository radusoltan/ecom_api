<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Test-only listener that sets app.bypass_rls on every request.
 *
 * This is registered only in the test environment via config/services_test.yaml.
 * It runs at high priority (before any provider/processor) to ensure RLS bypass
 * is available for tenant-related queries.
 */
final class TestRLSBypassListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        try {
            $this->connection->executeStatement("SET app.bypass_rls = 'true'");
        } catch (\Exception) {
            // Ignore if connection is not ready
        }
    }
}
