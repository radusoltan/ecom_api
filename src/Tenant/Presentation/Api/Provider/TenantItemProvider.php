<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Tenant\Application\Query\GetTenantByIdQuery;
use App\Tenant\Presentation\Api\TenantResource;
use App\Tenant\Presentation\Api\Transformer\TenantResourceTransformer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @implements ProviderInterface<TenantResource>
 */
final readonly class TenantItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?TenantResource {
        $tenantId = $uriVariables['id'] ?? null;

        if (null === $tenantId) {
            throw new \InvalidArgumentException('Tenant ID is required');
        }

        // Dispatch query to get tenant by ID
        $query = new GetTenantByIdQuery(tenantId: $tenantId);
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof StampInterface) {
            throw new \RuntimeException('No handler found for query');
        }

        $tenantDTO = $stamp->getResult();

        if (null === $tenantDTO) {
            return null;
        }

        return TenantResourceTransformer::fromDTO($tenantDTO);
    }
}
