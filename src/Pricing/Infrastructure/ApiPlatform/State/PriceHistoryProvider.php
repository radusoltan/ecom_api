<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PriceHistoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * API Platform state provider for price history.
 *
 * Handles GET requests for price history data.
 */
final class PriceHistoryProvider implements ProviderInterface
{
    public function __construct(
        private readonly PriceHistoryRepositoryInterface $priceHistoryRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        // Extract tenant ID from header
        $tenantIdString = $request->headers->get('X-Tenant-ID');
        if (null === $tenantIdString) {
            throw new \InvalidArgumentException('X-Tenant-ID header is required');
        }

        $tenantId = TenantId::fromString($tenantIdString);

        // Handle different operations
        if (isset($uriVariables['id'])) {
            // Single item (not implemented in repository, would need findById)
            return null;
        }

        if (isset($uriVariables['productId'])) {
            // Get price history for specific product
            $productId = ProductId::fromString($uriVariables['productId']);
            $limit = (int) $request->query->get('limit', 50);
            $offset = (int) $request->query->get('offset', 0);

            $priceChanges = $this->priceHistoryRepository->findByProductId(
                $productId,
                $tenantId,
                $limit,
                $offset
            );

            // Convert to entities for API Platform serialization
            return array_map(
                fn ($priceChange) => PriceHistoryEntity::fromPriceChange($priceChange, $tenantId),
                $priceChanges
            );
        }

        // Get all price history for tenant
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        if ($from && $to) {
            // Date range query
            $priceChanges = $this->priceHistoryRepository->findByDateRange(
                $tenantId,
                new \DateTimeImmutable($from),
                new \DateTimeImmutable($to),
                $limit,
                $offset
            );
        } else {
            // All for tenant
            $priceChanges = $this->priceHistoryRepository->findByTenant(
                $tenantId,
                $limit,
                $offset
            );
        }

        // Convert to entities for API Platform serialization
        return array_map(
            fn ($priceChange) => PriceHistoryEntity::fromPriceChange($priceChange, $tenantId),
            $priceChanges
        );
    }
}
