<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider for single fulfillment (GET /api/fulfillments/{id})
 */
final readonly class FulfillmentItemProvider implements ProviderInterface
{
    public function __construct(
        private FulfillmentRepositoryInterface $fulfillmentRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = $uriVariables['id'] ?? null;

        if ($id === null) {
            throw new NotFoundHttpException('Fulfillment ID is required');
        }

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantIdString = $context['tenant_id'] ?? null;
        if ($tenantIdString === null) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);
        $fulfillmentId = FulfillmentId::fromString($id);

        $fulfillment = $this->fulfillmentRepository->findById($fulfillmentId);

        if ($fulfillment === null) {
            throw new NotFoundHttpException(sprintf('Fulfillment with ID "%s" not found', $id));
        }

        // Verify tenant ownership
        if (!$fulfillment->tenantId()->equals($tenantId)) {
            throw new NotFoundHttpException(sprintf('Fulfillment with ID "%s" not found', $id));
        }

        // Convert domain model to entity
        return \App\Order\Infrastructure\Persistence\Doctrine\Entity\FulfillmentEntity::fromDomainModel($fulfillment);
    }
}
