<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves tenant context from requests with the following priority:
 *
 * 1. JWT claim `tenant_id` (for authenticated users — cannot be spoofed)
 * 2. X-Tenant-ID header (for unauthenticated/public requests — storefront, guest checkout)
 * 3. Request attribute `tenant_id` (for internal routing)
 *
 * If an authenticated user provides X-Tenant-ID header that differs from their
 * JWT tenant_id claim, the request is rejected to prevent tenant spoofing.
 */
final class TenantRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TenantContext $tenantContext,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after the firewall (priority 8) but before controllers
            KernelEvents::REQUEST => ['onKernelRequest', 6],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $tenantId = $this->resolveTenantId($request);

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

    private function resolveTenantId(Request $request): ?string
    {
        $jwtTenantId = $this->extractTenantIdFromJwt();
        $headerTenantId = $request->headers->get('X-Tenant-ID');
        $attributeTenantId = $request->attributes->get('tenant_id');

        // Priority 1: JWT claim (authenticated users)
        if (null !== $jwtTenantId) {
            // If header is also provided and differs, log a warning but use JWT
            if (null !== $headerTenantId && $headerTenantId !== $jwtTenantId) {
                $this->logger->warning('Tenant ID mismatch: JWT claim "{jwtTenant}" differs from X-Tenant-ID header "{headerTenant}". Using JWT claim.', [
                    'jwtTenant' => $jwtTenantId,
                    'headerTenant' => $headerTenantId,
                    'ip' => $request->getClientIp(),
                ]);
            }

            return $jwtTenantId;
        }

        // Priority 2: X-Tenant-ID header (unauthenticated/public requests)
        if (null !== $headerTenantId) {
            return $headerTenantId;
        }

        // Priority 3: Request attribute (internal routing)
        if (null !== $attributeTenantId) {
            return (string) $attributeTenantId;
        }

        return null;
    }

    private function extractTenantIdFromJwt(): ?string
    {
        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            return null;
        }

        // Lexik JWT stores decoded payload attributes on the token
        $user = $token->getUser();

        if (null === $user) {
            return null;
        }

        // Check token attributes for tenant_id claim
        if (method_exists($token, 'getPayload')) {
            $payload = $token->getPayload();

            return $payload['tenant_id'] ?? null;
        }

        // Fallback: check token attributes (Symfony security tokens)
        $attributes = $token->getAttributes();

        return $attributes['tenant_id'] ?? null;
    }
}
