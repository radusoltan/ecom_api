<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\ReadModel\AdminProductReadRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<object>
 */
final readonly class ProductCollectionProvider implements ProviderInterface
{
    public function __construct(
        private AdminProductReadRepository $readRepository,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        // Get tenant ID from context (injected by TenantContextProvider decorator)
        $tenantId = $context['tenant_id'] ?? null;

        if (null === $tenantId) {
            throw new \RuntimeException('Tenant ID is required but not provided in X-Tenant-ID header');
        }

        // Get current request to extract locale
        $request = $this->requestStack->getCurrentRequest();

        // Get locale from query parameter or Accept-Language header
        $locale = $request?->query->get('locale')
            ?? $request?->getPreferredLanguage(['en', 'fr', 'de'])
            ?? 'en';

        $page = max(1, (int) ($request?->query->get('page', 1) ?? 1));
        $itemsPerPage = max(1, min(1000, (int) ($request?->query->get('itemsPerPage', 1000) ?? 1000)));
        $sort = $request?->query->get('sort', 'newest');
        $search = $request?->query->get('search') ?? $request?->query->get('q');
        $activeOnly = $this->parseNullableBoolean(
            $request?->query->get('activeOnly') ?? $request?->query->get('active')
        ) ?? true;

        return $this->readRepository->findForAdmin(
            tenantId: $tenantId,
            page: $page,
            itemsPerPage: $itemsPerPage,
            sort: is_string($sort) ? $sort : 'newest',
            search: is_string($search) && '' !== $search ? $search : null,
            activeOnly: $activeOnly,
            locale: $locale,
        )['products'];
    }

    private function parseNullableBoolean(mixed $value): ?bool
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
    }
}
