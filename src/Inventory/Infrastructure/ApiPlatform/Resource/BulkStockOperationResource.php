<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use App\Inventory\Infrastructure\ApiPlatform\State\BulkReserveStockProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API Resource for Bulk Stock Operations.
 *
 * Handles bulk operations on multiple stock items in a single request.
 *
 * REST Endpoints:
 * - POST /api/stock/bulk-reserve - Reserve multiple items atomically
 *
 * GraphQL Operations:
 * - Mutation: bulkReserveStock - Reserve multiple items in one operation
 */
#[ApiResource(
    shortName: 'BulkStockOperation',
    operations: [
        new Post(
            uriTemplate: '/stock/bulk-reserve',
            processor: BulkReserveStockProcessor::class,
            name: 'bulk_reserve_stock',
        ),
    ],
    graphQlOperations: [
        new Mutation(
            name: 'bulkReserveStock',
            processor: BulkReserveStockProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
final class BulkStockOperationResource
{
    /**
     * @param array<BulkStockOperationItem>            $items
     * @param array<BulkStockOperationResultItem>|null $results
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Count(min: 1, max: 50)]
        public ?array $items = null,
        #[Assert\NotBlank]
        public ?string $referenceId = null,

        // Response fields
        public ?int $totalItems = null,
        public ?int $successCount = null,
        public ?int $failureCount = null,
        public ?array $results = null,
        public ?string $status = null,
    ) {
    }
}
