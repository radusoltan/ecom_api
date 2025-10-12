<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider for ProductEntity item operations (single product by ID)
 * Ensures tenant isolation by checking tenant_id from X-Tenant-ID header
 *
 * @implements ProviderInterface<ProductEntity>
 */
final readonly class ProductItemProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get tenant ID from context (injected by TenantContextProvider decorator)
        $tenantId = $context['tenant_id'] ?? null;

        if ($tenantId === null) {
            throw new \RuntimeException('Tenant ID is required but not provided in X-Tenant-ID header');
        }

        $productId = $uriVariables['id'] ?? null;

        if ($productId === null) {
            throw new \RuntimeException('Product ID is required');
        }

        $repository = $this->entityManager->getRepository(ProductEntity::class);

        // Find product by ID AND tenant_id to enforce tenant isolation
        return $repository->findOneBy([
            'id' => $productId,
            'tenantId' => $tenantId,
        ]);
    }
}
