<?php

declare(strict_types=1);

namespace App\Search\Application\Query;

use App\Internationalization\Domain\Model\Locale;
use App\Search\Domain\Model\SearchQuery;
use App\Search\Domain\Model\SearchResult;
use App\Search\Domain\Service\SearchServiceInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * SearchProductsHandler.
 *
 * Handles full-text product search requests.
 */
#[AsMessageHandler]
final readonly class SearchProductsHandler
{
    public function __construct(
        private SearchServiceInterface $searchService,
    ) {
    }

    public function __invoke(SearchProducts $query): SearchResult
    {
        $searchQuery = new SearchQuery(
            tenantId: TenantId::fromString($query->tenantId),
            query: $query->query,
            locale: Locale::fromString($query->locale),
            page: $query->page,
            perPage: $query->perPage,
            sortBy: $query->sortBy,
            sortOrder: $query->sortOrder,
            filters: $query->filters,
        );

        return $this->searchService->search($searchQuery);
    }
}
