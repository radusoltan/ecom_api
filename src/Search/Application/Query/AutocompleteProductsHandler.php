<?php

declare(strict_types=1);

namespace App\Search\Application\Query;

use App\Search\Domain\Model\ProductSearchHit;
use App\Search\Domain\Service\SearchServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * AutocompleteProductsHandler.
 *
 * Handles autocomplete/suggestion requests for products.
 */
#[AsMessageHandler]
final readonly class AutocompleteProductsHandler
{
    public function __construct(
        private SearchServiceInterface $searchService,
    ) {
    }

    /**
     * @return array<ProductSearchHit>
     */
    public function __invoke(AutocompleteProducts $query): array
    {
        return $this->searchService->autocomplete(
            query: $query->query,
            tenantId: $query->tenantId,
            locale: $query->locale,
            limit: $query->limit
        );
    }
}
