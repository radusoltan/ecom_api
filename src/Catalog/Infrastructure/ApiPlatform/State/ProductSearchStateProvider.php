<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductSearchResource;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<ProductSearchResource>
 */
final readonly class ProductSearchStateProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
        private Connection $connection,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return [];
        }

        // Get tenant ID from header
        $tenantIdString = $request->headers->get('X-Tenant-ID');
        if (null === $tenantIdString) {
            throw new \InvalidArgumentException('X-Tenant-ID header is required');
        }

        $tenantId = TenantId::fromString($tenantIdString);

        // Get locale from query parameter or Accept-Language header
        $localeString = $request->query->get('locale');
        if (null === $localeString) {
            $localeString = $this->getLocaleFromAcceptLanguage($request->headers->get('Accept-Language'));
        }
        // Locale::fromString now accepts both "en" and "en_US" formats
        $locale = Locale::fromString($localeString ?? 'en');

        // Build query parameters
        $query = new SearchProductsQuery(
            tenantId: $tenantId,
            locale: $locale,
            query: $request->query->get('q'),
            categoryIds: $this->parseCategoryIds($request->query->get('category')),
            minPrice: null !== $request->query->get('minPrice') ? (float) $request->query->get('minPrice') : null,
            maxPrice: null !== $request->query->get('maxPrice') ? (float) $request->query->get('maxPrice') : null,
            status: $request->query->get('status', 'active'),
            sortBy: $request->query->get('sortBy', 'relevance'),
            page: $request->query->getInt('page', 1),
            limit: min($request->query->getInt('limit', 20), 100), // Max 100 items per page
            options: $this->parseOptions($request->query->all()),
            minRating: null !== $request->query->get('minRating') ? (int) $request->query->get('minRating') : null,
            featured: '1' === $request->query->get('featured') || 'true' === $request->query->get('featured') ? true : null,
        );

        // Execute query
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $result = $handledStamp->getResult();

        // Get inventory data for all products
        $productIds = array_map(fn ($p) => $p->id, $result->products);
        $inventoryData = $this->getInventoryData($productIds);

        // Convert to API resources
        $resources = [];

        foreach ($result->products as $productDto) {
            $resource = new ProductSearchResource();
            $resource->id = $productDto->id; // For API Platform identifier
            $resource->productId = $productDto->id;
            $resource->tenantId = $productDto->tenantId;
            $resource->sku = $productDto->sku;
            $resource->name = $productDto->name;
            $resource->description = $productDto->description;
            $resource->slug = $productDto->slug;
            $resource->price = $productDto->priceInCents / 100;
            $resource->priceMinor = $productDto->priceInCents;
            $resource->finalPrice = $productDto->priceInCents / 100; // TODO: Apply promotions/discounts
            $resource->finalPriceMinor = $productDto->priceInCents;
            $resource->totalDiscountMinor = 0; // TODO: Calculate from promotions
            $resource->discountPercent = 0.0; // TODO: Calculate from promotions
            $resource->currency = $productDto->currency;
            $resource->status = $productDto->status;

            // Get inventory info for this product
            $inventory = $inventoryData[$productDto->id] ?? null;
            $resource->inStock = null !== $inventory && $inventory['totalAvailable'] > 0;
            $resource->trackInventory = null !== $inventory; // If has inventory records, we track it
            $resource->allowBackorder = false; // TODO: Get from product config
            $resource->inventoryTotalAvailable = $inventory['totalAvailable'] ?? 0;
            $resource->inventoryIsLow = null !== $inventory && $inventory['totalAvailable'] <= $inventory['lowStockThreshold'];
            $resource->inventoryWarehouses = $inventory['warehouses'] ?? [];

            $resource->categoryIds = $productDto->categoryIds;
            $resource->imageUrl = $productDto->imageUrl;
            $resource->locale = $productDto->locale;
            $resource->score = $productDto->score;

            // Rating and review information
            $resource->averageRating = $productDto->averageRating;
            $resource->reviewCount = $productDto->reviewCount;

            // Featured flag
            $resource->isFeatured = $productDto->isFeatured;

            $resources[] = $resource;
        }

        // Add metadata to first resource for collection context
        if (count($resources) > 0) {
            $resources[0]->total = $result->total;
            $resources[0]->page = $result->page;
            $resources[0]->limit = $result->limit;
            $resources[0]->totalPages = $result->getTotalPages();
            $resources[0]->hasNextPage = $result->hasNextPage();
            $resources[0]->hasPreviousPage = $result->hasPreviousPage();
            $resources[0]->facets = $result->facets;
        }

        return $resources;
    }

    private function parseCategoryIds(?string $categoryIdsString): ?array
    {
        if (null === $categoryIdsString || '' === $categoryIdsString) {
            return null;
        }

        return array_filter(
            array_map('trim', explode(',', $categoryIdsString)),
            fn ($id) => '' !== $id
        );
    }

    /**
     * Parse option parameters from query string
     * Converts option[color]=red,blue&option[size]=m,l to ['color' => ['red', 'blue'], 'size' => ['m', 'l']].
     *
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, array<string>>|null
     */
    private function parseOptions(array $queryParams): ?array
    {
        $options = [];

        foreach ($queryParams as $key => $value) {
            // Check if parameter is in format "option[optionCode]"
            if (preg_match('/^option\[([a-z_]+)\]$/i', $key, $matches)) {
                $optionCode = $matches[1];

                // Parse comma-separated values
                if (is_string($value) && '' !== $value) {
                    $values = array_filter(
                        array_map('trim', explode(',', $value)),
                        fn ($v) => '' !== $v
                    );

                    if (!empty($values)) {
                        $options[$optionCode] = array_values($values);
                    }
                }
            }
        }

        return !empty($options) ? $options : null;
    }

    /**
     * Get inventory data for a list of product IDs
     * Returns: [productId => ['totalAvailable' => int, 'lowStockThreshold' => int, 'warehouses' => [...]]].
     */
    private function getInventoryData(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Query stock items with warehouse info
        $sql = '
            SELECT
                si.product_id,
                si.on_hand,
                si.reserved,
                si.allocated,
                si.low_stock_threshold,
                w.id as warehouse_id,
                w.name as warehouse_name,
                w.code as warehouse_code
            FROM stock_items si
            JOIN warehouses w ON si.warehouse_id = w.id
            WHERE si.product_id IN (?)
            AND w.is_active = true
            ORDER BY si.product_id, w.priority ASC
        ';

        $results = $this->connection->executeQuery(
            $sql,
            [$productIds],
            [ArrayParameterType::STRING]
        )->fetchAllAssociative();

        // Aggregate by product ID
        $inventoryData = [];
        foreach ($results as $row) {
            $productId = $row['product_id'];
            $available = (int) $row['on_hand'] - (int) $row['reserved'] - (int) $row['allocated'];

            if (!isset($inventoryData[$productId])) {
                $inventoryData[$productId] = [
                    'totalAvailable' => 0,
                    'lowStockThreshold' => (int) $row['low_stock_threshold'],
                    'warehouses' => [],
                ];
            }

            $inventoryData[$productId]['totalAvailable'] += $available;
            $inventoryData[$productId]['warehouses'][] = [
                'warehouse_id' => $row['warehouse_id'],
                'warehouse_name' => $row['warehouse_name'],
                'warehouse_code' => $row['warehouse_code'],
                'available' => $available,
                'on_hand' => (int) $row['on_hand'],
                'reserved' => (int) $row['reserved'],
                'allocated' => (int) $row['allocated'],
            ];
        }

        return $inventoryData;
    }

    private function getLocaleFromAcceptLanguage(?string $acceptLanguage): ?string
    {
        if (null === $acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,ro;q=0.8")
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            // Extract language code before quality value
            $langCode = explode(';', trim($language))[0];

            // Convert to our locale format (en-US -> en_US or just "en")
            $locale = str_replace('-', '_', $langCode);

            // Try to create Locale (now accepts both "en" and "en_US" formats)
            try {
                Locale::fromString($locale);

                return $locale;
            } catch (\InvalidArgumentException) {
                // If full locale fails, try just the language part
                $parts = explode('_', $locale);
                if (count($parts) > 1) {
                    try {
                        Locale::fromString($parts[0]);

                        return $parts[0];
                    } catch (\InvalidArgumentException) {
                        continue;
                    }
                }

                continue;
            }
        }

        return null;
    }
}
