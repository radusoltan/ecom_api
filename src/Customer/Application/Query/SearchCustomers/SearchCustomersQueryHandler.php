<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\SearchCustomers;

use App\Customer\Application\DTO\CustomerSummaryDTO;
use App\Customer\Domain\Repository\CustomerSearchRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerSearchCriteria;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Search Customers Query Handler.
 *
 * Handles customer search queries with filtering and pagination.
 */
final readonly class SearchCustomersQueryHandler
{
    public function __construct(
        private CustomerSearchRepositoryInterface $searchRepository,
    ) {
    }

    public function __invoke(SearchCustomersQuery $query): SearchCustomersResult
    {
        $criteria = new CustomerSearchCriteria(
            tenantId: TenantId::fromString($query->tenantId),
            searchTerm: $query->searchTerm,
            status: $query->status,
            segment: $query->segment,
            registeredFrom: $query->registeredFrom,
            registeredTo: $query->registeredTo,
            hasOrders: $query->hasOrders,
            minOrderCount: $query->minOrderCount,
            maxOrderCount: $query->maxOrderCount,
            sortBy: $query->sortBy,
            sortOrder: $query->sortOrder,
            page: $query->page,
            limit: $query->limit
        );

        $result = $this->searchRepository->search($criteria);

        $items = array_map(
            fn ($customer) => CustomerSummaryDTO::fromDomainModel($customer),
            $result['customers']
        );

        return SearchCustomersResult::create(
            items: $items,
            total: $result['total'],
            page: $query->page,
            limit: $query->limit
        );
    }
}
