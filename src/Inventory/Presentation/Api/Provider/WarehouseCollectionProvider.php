<?php

declare(strict_types=1);

namespace App\Inventory\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Application\Query\GetAllWarehouses\GetAllWarehouses;
use App\Inventory\Presentation\Api\Transformer\WarehouseResourceTransformer;
use App\Inventory\Presentation\Api\WarehouseResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<WarehouseResource>
 */
final readonly class WarehouseCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {}

    /**
     * @return WarehouseResource[]
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array {
        // Get tenant ID from context (set by TenantContextProvider)
        $tenantId = $context['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \RuntimeException('Tenant ID not found in context');
        }

        // Check for 'active' query parameter
        $activeOnly = $context['filters']['active'] ?? false;

        // Dispatch query to get all warehouses for tenant
        $query = new GetAllWarehouses(
            TenantId::fromString($tenantId),
            (bool) $activeOnly
        );

        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $warehouseDTOs = $stamp->getResult();

        return array_map(
            fn ($dto) => WarehouseResourceTransformer::fromDTO($dto),
            $warehouseDTOs
        );
    }
}
