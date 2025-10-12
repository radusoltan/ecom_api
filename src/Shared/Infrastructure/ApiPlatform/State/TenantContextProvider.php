<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorator that injects tenant_id from X-Tenant-ID header into API Platform context
 *
 * This allows state providers to access tenant_id via $context['tenant_id'] for multi-tenancy.
 *
 * @template T
 * @implements ProviderInterface<T>
 */
final readonly class TenantContextProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null && $request->headers->has('X-Tenant-ID')) {
            $context['tenant_id'] = $request->headers->get('X-Tenant-ID');
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
