<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class TenantRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $tenantId = $this->extractTenantId($request);

        if (null === $tenantId) {
            return;
        }

        try {
            $this->tenantContext->setCurrentTenant(TenantId::fromString($tenantId));
        } catch (\InvalidArgumentException) {
            // Ignore invalid tenant identifiers; leave context untouched.
        }
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->tenantContext->clearCurrentTenant();
    }

    private function extractTenantId(Request $request): ?string
    {
        if ($request->headers->has('X-Tenant-ID')) {
            return $request->headers->get('X-Tenant-ID');
        }

        return $request->attributes->get('tenant_id');
    }
}
