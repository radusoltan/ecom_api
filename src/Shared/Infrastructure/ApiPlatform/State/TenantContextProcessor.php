<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorator that injects tenant_id from X-Tenant-ID header into API Platform context
 *
 * This allows state processors to access tenant_id via $context['tenant_id'] for multi-tenancy.
 *
 * @template T
 * @implements ProcessorInterface<T>
 */
final readonly class TenantContextProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null && $request->headers->has('X-Tenant-ID')) {
            $context['tenant_id'] = $request->headers->get('X-Tenant-ID');
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
