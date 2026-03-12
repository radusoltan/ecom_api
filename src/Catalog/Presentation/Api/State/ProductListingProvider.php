<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\ReadModel\ProductListingReadRepository;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProductListingProvider implements ProviderInterface
{
    private const DEFAULT_ITEMS_PER_PAGE = 24;

    public function __construct(
        private readonly ProductListingReadRepository $readRepository,
        private readonly TenantContext $tenantContext,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return [];
        }

        // Try to get tenant ID from context first, fallback to header
        $tenantId = $this->tenantContext->getTenantId();
        if (!$tenantId && $request->headers->has('X-Tenant-ID')) {
            $tenantId = $request->headers->get('X-Tenant-ID');
        }

        if (!$tenantId) {
            return [];
        }

        // Get locale from request
        $locale = $request->headers->get('Accept-Language', 'en');
        $locale = $this->parseLocale($locale);

        // Parse filters from query parameters
        $filters = $this->parseFilters($request);
        $page = (int) $request->query->get('page', 1);
        $itemsPerPage = (int) $request->query->get('itemsPerPage', self::DEFAULT_ITEMS_PER_PAGE);
        $itemsPerPage = min($itemsPerPage, 48); // Max 48 items per page

        $sort = $filters['sort'] ?? 'newest';
        $result = $this->readRepository->findForStorefront(
            tenantId: $tenantId,
            filters: $filters,
            page: $page,
            itemsPerPage: $itemsPerPage,
            sort: is_string($sort) ? $sort : 'newest',
            locale: $locale,
        );
        $products = $result['products'];

        return $products;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilters(Request $request): array
    {
        return [
            'q' => $request->query->get('q'),
            'category' => $request->query->get('category'),
            'priceMin' => $request->query->get('priceMin'),
            'priceMax' => $request->query->get('priceMax'),
            'sort' => $request->query->get('sort', 'newest'),
            'attributes' => $request->query->all('attributes'),
        ];
    }

    private function parseLocale(string $acceptLanguage): string
    {
        $locales = explode(',', $acceptLanguage);
        if (empty($locales)) {
            return 'en';
        }

        $locale = explode(';', $locales[0])[0];
        $locale = strtolower(trim($locale));
        $locale = explode('-', $locale)[0] ?? $locale;

        return '' !== $locale ? $locale : 'en';
    }
}
