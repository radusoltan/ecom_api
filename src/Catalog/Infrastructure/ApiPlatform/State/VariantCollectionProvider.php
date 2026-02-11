<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\Query\GetVariantsByProduct;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\VariantEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Provider for variant collection.
 */
final readonly class VariantCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack
    ) {
    }

    /**
     * @return VariantEntity[]|null
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array|null {
        // Get tenant ID from request header
        $request = $this->requestStack->getCurrentRequest();
        $tenantIdString = $request?->headers->get('X-Tenant-ID');

        if (!$tenantIdString) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        // Get product ID from URI variables or query parameters
        $productIdString = null;

        // Check URI variables first (for collection routes like /products/{productId}/variants)
        if (isset($uriVariables['productId'])) {
            $productIdString = (string) $uriVariables['productId'];
        }

        // Check query parameters (for routes like /variants?productId=xxx)
        if (!$productIdString && $request) {
            $queryProductId = $request->query->get('productId');
            $productIdString = is_string($queryProductId) ? $queryProductId : null;
        }

        // For single-item operations (GET/PATCH/DELETE with 'id' in URI), productId is optional
        // The specific processors will handle these operations
        if (!$productIdString && isset($uriVariables['id'])) {
            // This is a single-item operation, return null to let the default provider handle it
            return null;
        }

        if (!$productIdString) {
            throw new \InvalidArgumentException('Product ID is required in URI');
        }

        // Get optional query parameters for filtering
        $filters = [];
        $activeOnly = null;
        $page = 1;
        $limit = 50;

        if ($request) {
            // Parse filters from query string (e.g., ?color=red&size=xl)
            $queryParams = $request->query->all();
            foreach ($queryParams as $key => $value) {
                if ('active' === $key) {
                    $activeOnly = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ('page' === $key) {
                    $page = max(1, (int) $value);
                } elseif ('limit' === $key) {
                    $limit = max(1, min(100, (int) $value));
                } elseif (!in_array($key, ['active', 'page', 'limit'], true)) {
                    // Treat other parameters as option filters
                    $filters[$key] = (string) $value;
                }
            }
        }

        // Create and dispatch query
        $query = new GetVariantsByProduct(
            productId: ProductId::fromString($productIdString),
            tenantId: TenantId::fromString($tenantIdString),
            filters: $filters,
            activeOnly: $activeOnly,
            page: $page,
            limit: $limit
        );

        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $result = $stamp->getResult();
        $variantDTOs = $result['variants'] ?? [];

        // Convert DTOs to entities
        $variantEntities = [];
        foreach ($variantDTOs as $dto) {
            $entity = new VariantEntity();

            // Use reflection to set private properties
            $reflection = new \ReflectionClass($entity);

            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($entity, $dto->id);

            $skuProperty = $reflection->getProperty('sku');
            $skuProperty->setAccessible(true);
            $skuProperty->setValue($entity, $dto->sku);

            $optionValueMapProperty = $reflection->getProperty('optionValueMap');
            $optionValueMapProperty->setAccessible(true);
            $optionValueMapProperty->setValue($entity, $dto->optionValueMap);

            $priceAmountProperty = $reflection->getProperty('priceAmount');
            $priceAmountProperty->setAccessible(true);
            $priceAmountProperty->setValue($entity, $dto->priceAmount);

            $priceCurrencyProperty = $reflection->getProperty('priceCurrency');
            $priceCurrencyProperty->setAccessible(true);
            $priceCurrencyProperty->setValue($entity, $dto->priceCurrency);

            $stockOnHandProperty = $reflection->getProperty('stockOnHand');
            $stockOnHandProperty->setAccessible(true);
            $stockOnHandProperty->setValue($entity, $dto->stockQuantity);

            $trackInventoryProperty = $reflection->getProperty('trackInventory');
            $trackInventoryProperty->setAccessible(true);
            $trackInventoryProperty->setValue($entity, $dto->trackInventory);

            $allowBackorderProperty = $reflection->getProperty('allowBackorder');
            $allowBackorderProperty->setAccessible(true);
            $allowBackorderProperty->setValue($entity, $dto->allowBackorder);

            $isActiveProperty = $reflection->getProperty('isActive');
            $isActiveProperty->setAccessible(true);
            $isActiveProperty->setValue($entity, $dto->isActive);

            $imagesProperty = $reflection->getProperty('images');
            $imagesProperty->setAccessible(true);
            $imagesProperty->setValue($entity, $dto->images);

            $variantEntities[] = $entity;
        }

        return $variantEntities;
    }
}
