<?php

declare(strict_types=1);

namespace App\Returns\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Returns\Application\Query\GetAllReturnRequests;
use App\Returns\Application\Query\GetReturnRequestsByOrderId;
use App\Returns\Presentation\Api\Transformer\ReturnRequestResourceTransformer;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Provider for retrieving a collection of return requests.
 *
 * Supports filtering by:
 * - orderId: Get all returns for a specific order
 * - status: Get all returns with a specific status
 * - tenantId: Get all returns for a tenant (required)
 */
final class ReturnRequestCollectionProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $queryBus
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        // Filter by order ID if provided
        if (isset($filters['orderId'])) {
            $dtos = $this->handle(new GetReturnRequestsByOrderId($filters['orderId']));
        } else {
            // Get all returns for tenant (with optional status filter)
            $tenantId = $context['tenant_id'] ?? throw new \RuntimeException('Tenant ID is required');
            $status = $filters['status'] ?? null;

            $dtos = $this->handle(new GetAllReturnRequests($tenantId, $status));
        }

        return array_map(
            fn($dto) => ReturnRequestResourceTransformer::fromDTO($dto),
            $dtos
        );
    }
}
