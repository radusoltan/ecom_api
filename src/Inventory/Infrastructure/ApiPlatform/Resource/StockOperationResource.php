<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use App\Inventory\Infrastructure\ApiPlatform\State\AllocateStockProcessor;
use App\Inventory\Infrastructure\ApiPlatform\State\ReleaseStockProcessor;
use App\Inventory\Infrastructure\ApiPlatform\State\ReserveStockProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API Resource for Stock Operations.
 *
 * Handles reserve, allocate, and release operations on stock items.
 *
 * REST Endpoints:
 * - POST /api/stock/reserve - Reserve stock (soft hold for cart)
 * - POST /api/stock/allocate - Allocate stock (hard allocation for order)
 * - POST /api/stock/release - Release reserved/allocated stock
 *
 * GraphQL Operations:
 * - Mutation: reserveStock - Reserve stock for cart
 * - Mutation: allocateStock - Allocate stock for order
 * - Mutation: releaseStock - Release reserved/allocated stock
 */
#[ApiResource(
    shortName: 'StockOperation',
    operations: [
        new Post(
            uriTemplate: '/stock/reserve',
            processor: ReserveStockProcessor::class,
            name: 'reserve_stock',
        ),
        new Post(
            uriTemplate: '/stock/allocate',
            processor: AllocateStockProcessor::class,
            name: 'allocate_stock',
        ),
        new Post(
            uriTemplate: '/stock/release',
            processor: ReleaseStockProcessor::class,
            name: 'release_stock',
        ),
    ],
    graphQlOperations: [
        new Mutation(
            name: 'reserveStock',
            processor: ReserveStockProcessor::class,
        ),
        new Mutation(
            name: 'allocateStock',
            processor: AllocateStockProcessor::class,
        ),
        new Mutation(
            name: 'releaseStock',
            processor: ReleaseStockProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
final class StockOperationResource
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public ?string $productId = null,
        #[Assert\NotBlank]
        #[Assert\Ulid]
        public ?string $warehouseId = null,
        #[Assert\NotBlank]
        #[Assert\Positive]
        public ?int $quantity = null,
        #[Assert\NotBlank]
        public ?string $referenceId = null, // reservation ID, order ID, or reason

        // Response fields
        public ?string $message = null,
        public ?int $availableQuantity = null,
    ) {
    }
}
