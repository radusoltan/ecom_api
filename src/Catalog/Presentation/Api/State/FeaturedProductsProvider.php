<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\ReadModel\ProductListingReadRepository;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\RequestStack;

final class FeaturedProductsProvider implements ProviderInterface
{
    private const DEFAULT_LIMIT = 8;

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

        $limit = (int) $request->query->get('limit', self::DEFAULT_LIMIT);
        $limit = min($limit, 20); // Max 20 items

        $dtos = $this->readRepository->findForStorefront(
            tenantId: $tenantId,
            filters: ['isFeatured' => true],
            page: 1,
            itemsPerPage: $limit,
            sort: 'newest',
            locale: $locale,
        )['products'];

        return $dtos;
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
