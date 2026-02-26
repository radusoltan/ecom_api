<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds tenant_id claim to JWT payload during token creation.
 *
 * The tenant_id is derived from the X-Tenant-ID header present during login.
 * Once embedded in the JWT, subsequent requests will use the JWT claim
 * instead of trusting the client-supplied header.
 */
final readonly class TenantJwtEventListener
{
    public function __construct(
        private RequestStack $requestStack,
        private TenantContext $tenantContext,
    ) {
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', priority: 0)]
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (null === $tenantId) {
            // Fallback: try to get from the request header
            $request = $this->requestStack->getCurrentRequest();
            $tenantId = $request?->headers->get('X-Tenant-ID');
        }

        if (null !== $tenantId) {
            $payload = $event->getData();
            $payload['tenant_id'] = $tenantId;
            $event->setData($payload);
        }
    }
}
