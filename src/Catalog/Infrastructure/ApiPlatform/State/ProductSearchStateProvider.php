<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductSearchResource;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
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
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return [];
        }

        // Get tenant ID from header
        $tenantIdString = $request->headers->get('X-Tenant-ID');
        if ($tenantIdString === null) {
            throw new \InvalidArgumentException('X-Tenant-ID header is required');
        }

        $tenantId = TenantId::fromString($tenantIdString);

        // Get locale from query parameter or Accept-Language header
        $localeString = $request->query->get('locale');
        if ($localeString === null) {
            $localeString = $this->getLocaleFromAcceptLanguage($request->headers->get('Accept-Language'));
        }
        $locale = Locale::fromString($localeString ?? 'en_US');

        // Build query parameters
        $query = new SearchProductsQuery(
            tenantId: $tenantId,
            locale: $locale,
            query: $request->query->get('q'),
            categoryIds: $this->parseCategoryIds($request->query->get('category')),
            minPrice: $request->query->get('minPrice') !== null ? (float) $request->query->get('minPrice') : null,
            maxPrice: $request->query->get('maxPrice') !== null ? (float) $request->query->get('maxPrice') : null,
            status: $request->query->get('status', 'active'),
            sortBy: $request->query->get('sortBy', 'relevance'),
            page: $request->query->getInt('page', 1),
            limit: min($request->query->getInt('limit', 20), 100), // Max 100 items per page
        );

        // Execute query
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $result = $handledStamp->getResult();

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
            $resource->price = $productDto->price;
            $resource->currency = $productDto->currency;
            $resource->status = $productDto->status;
            $resource->categoryIds = $productDto->categoryIds;
            $resource->imageUrl = $productDto->imageUrl;
            $resource->locale = $productDto->locale;
            $resource->score = $productDto->score;

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
        if ($categoryIdsString === null || $categoryIdsString === '') {
            return null;
        }

        return array_filter(
            array_map('trim', explode(',', $categoryIdsString)),
            fn($id) => $id !== ''
        );
    }

    private function getLocaleFromAcceptLanguage(?string $acceptLanguage): ?string
    {
        if ($acceptLanguage === null) {
            return null;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,ro;q=0.8")
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            // Extract language code before quality value
            $langCode = explode(';', trim($language))[0];

            // Convert to our locale format (en-US -> en_US)
            $locale = str_replace('-', '_', $langCode);

            // Validate against supported locales
            try {
                Locale::fromString($locale);
                return $locale;
            } catch (\InvalidArgumentException) {
                // Try base language (en_US -> en_US is already base, but en -> en_US)
                if (strlen($locale) === 2) {
                    $baseLocale = strtolower($locale) . '_' . strtoupper($locale);
                    try {
                        Locale::fromString($baseLocale);
                        return $baseLocale;
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
