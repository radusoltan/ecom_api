<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Search\Application\Query\AutocompleteProducts;
use App\Search\Application\Query\SearchProducts;
use App\Search\Presentation\Api\Resource\SearchResultResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * SearchProvider.
 *
 * API Platform state provider for search operations.
 */
final class SearchProvider implements ProviderInterface
{
    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \RuntimeException('No request available');
        }

        $tenantId = $this->getTenantIdFromContext($context);
        $locale = $request->headers->get('Accept-Language') ?? 'en';

        // Extract locale code (e.g., "en-US" -> "en")
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, '_')) {
            $locale = explode('_', $locale)[0];
        }

        // Determine if this is autocomplete or full search
        $uriTemplate = $operation->getUriTemplate();
        if (null !== $uriTemplate && str_contains($uriTemplate, 'autocomplete')) {
            return $this->handleAutocomplete($request, $tenantId, $locale);
        }

        return $this->handleSearch($request, $tenantId, $locale);
    }

    private function handleSearch($request, string $tenantId, string $locale): SearchResultResource
    {
        $query = $request->query->get('q', '');
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 24);
        $sortBy = $request->query->get('sort_by', 'relevance');
        $sortOrder = $request->query->get('sort_order', 'desc');

        // Extract filters
        $filters = [];
        if ($request->query->has('filter')) {
            $filterParams = $request->query->all('filter');
            if (isset($filterParams['category'])) {
                $filters['category'] = $filterParams['category'];
            }
            if (isset($filterParams['price_min'])) {
                // price_min/price_max are accepted as integer cents (e.g. 1000 = $10.00)
                $filters['price_min'] = (int) $filterParams['price_min'];
            }
            if (isset($filterParams['price_max'])) {
                $filters['price_max'] = (int) $filterParams['price_max'];
            }
            if (isset($filterParams['in_stock_only'])) {
                $filters['in_stock_only'] = (bool) $filterParams['in_stock_only'];
            }
        }

        $searchQuery = new SearchProducts(
            tenantId: $tenantId,
            query: $query,
            locale: $locale,
            page: $page,
            perPage: $perPage,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
            filters: $filters,
        );

        $envelope = $this->queryBus->dispatch($searchQuery);
        $result = $envelope->last(HandledStamp::class)?->getResult();

        if (null === $result) {
            throw new \RuntimeException('Search query handler did not return a result');
        }

        // Convert domain SearchResult to API resource
        $resultArray = $result->toArray();

        return new SearchResultResource(
            hits: $resultArray['hits'],
            total: $resultArray['total'],
            page: $resultArray['page'],
            perPage: $resultArray['perPage'],
            totalPages: $resultArray['totalPages'],
            facets: $resultArray['facets'],
            took: $resultArray['took'],
        );
    }

    private function handleAutocomplete($request, string $tenantId, string $locale): array
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 5);

        $autocompleteQuery = new AutocompleteProducts(
            tenantId: $tenantId,
            query: $query,
            locale: $locale,
            limit: $limit,
        );

        $envelope = $this->queryBus->dispatch($autocompleteQuery);
        $results = $envelope->last(HandledStamp::class)?->getResult();

        if (null === $results) {
            throw new \RuntimeException('Autocomplete query handler did not return a result');
        }

        // Convert ProductSearchHit[] to array
        return array_map(fn ($hit) => $hit->toArray(), $results);
    }

    private function getTenantIdFromContext(array $context): string
    {
        if (isset($context['tenant_id'])) {
            return $context['tenant_id'];
        }

        throw new BadRequestHttpException('Tenant ID not found in context. Ensure X-Tenant-ID header is provided.');
    }
}
