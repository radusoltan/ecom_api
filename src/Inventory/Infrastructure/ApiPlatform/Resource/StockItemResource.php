<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use App\Inventory\Infrastructure\ApiPlatform\State\StockItemProvider;
use App\Inventory\Infrastructure\ApiPlatform\State\CreateStockItemProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API Resource for Stock Items
 *
 * Represents inventory stock for a product at a specific warehouse.
 *
 * REST Endpoints:
 * - GET /api/stock-items/{id} - Get stock item details
 * - GET /api/stock-items?productId=xxx&warehouseId=yyy - Query stock
 * - POST /api/stock-items - Create new stock item
 *
 * GraphQL Operations:
 * - Query: stockItem(id: ID!) - Get single stock item
 * - Query: stockItems - List all stock items (with pagination)
 * - Mutation: createStockItem - Create new stock item
 */
#[ApiResource(
    shortName: 'StockItem',
    operations: [
        new Get(
            uriTemplate: '/stock-items/{id}',
            provider: StockItemProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/stock-items',
            provider: StockItemProvider::class,
        ),
        new Post(
            uriTemplate: '/stock-items',
            processor: CreateStockItemProcessor::class,
            validationContext: ['groups' => ['Default', 'create']],
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            provider: StockItemProvider::class,
        ),
        new QueryCollection(
            name: 'collection_query',
            provider: StockItemProvider::class,
        ),
        new Mutation(
            name: 'create',
            processor: CreateStockItemProcessor::class,
        ),
    ],
    paginationEnabled: true,
)]
final class StockItemResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public ?string $id = null,

        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid]
        public ?string $tenantId = null,

        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid]
        public ?string $productId = null,

        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Ulid]
        public ?string $warehouseId = null,

        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\PositiveOrZero]
        public ?int $initialQuantity = null,

        #[Assert\PositiveOrZero]
        public ?int $lowStockThreshold = 10,

        // Read-only properties (populated by provider)
        public ?int $onHand = null,
        public ?int $reserved = null,
        public ?int $allocated = null,
        public ?int $available = null,
        public ?bool $isLowStock = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }
}
