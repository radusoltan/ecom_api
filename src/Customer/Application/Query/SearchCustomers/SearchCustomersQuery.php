<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\SearchCustomers;

/**
 * Search Customers Query.
 *
 * Query to search and filter customers with pagination.
 */
final readonly class SearchCustomersQuery
{
    public function __construct(
        public string $tenantId,
        public ?string $searchTerm = null,
        public ?string $status = null,
        public ?string $segment = null,
        public ?\DateTimeImmutable $registeredFrom = null,
        public ?\DateTimeImmutable $registeredTo = null,
        public ?bool $hasOrders = null,
        public ?int $minOrderCount = null,
        public ?int $maxOrderCount = null,
        public string $sortBy = 'createdAt',
        public string $sortOrder = 'DESC',
        public int $page = 1,
        public int $limit = 20
    ) {
    }
}
