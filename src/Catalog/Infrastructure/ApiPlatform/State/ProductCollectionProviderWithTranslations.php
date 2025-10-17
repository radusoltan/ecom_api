<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Infrastructure\Cache\I18nCacheService;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntityWithTranslations;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * API Platform provider for retrieving products with locale-aware translations
 * Resolves locale from Accept-Language header and applies translations
 */
final readonly class ProductCollectionProviderWithTranslations implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private LocalizationPolicy $localizationPolicy,
        private I18nCacheService $cacheService
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get tenant ID from header
        $tenantId = $request?->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        // Resolve locale from Accept-Language header or query parameter
        $locale = $this->resolveLocale($request);
        $tenantIdVo = TenantId::fromString($tenantId);

        // Build query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(ProductEntityWithTranslations::class, 'p')
            ->where('p.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId);

        // Apply filters from query parameters
        if ($request) {
            // Filter by active status
            if ($request->query->has('active')) {
                $qb->andWhere('p.active = :active')
                    ->setParameter('active', filter_var($request->query->get('active'), FILTER_VALIDATE_BOOLEAN));
            }

            // Filter by featured
            if ($request->query->has('featured')) {
                $qb->andWhere('p.isFeatured = :featured')
                    ->setParameter('featured', filter_var($request->query->get('featured'), FILTER_VALIDATE_BOOLEAN));
            }

            // Filter by category
            if ($request->query->has('category')) {
                $qb->andWhere('p.categoryId = :categoryId')
                    ->setParameter('categoryId', $request->query->get('category'));
            }

            // Search by name (in default field for now)
            if ($request->query->has('search')) {
                $qb->andWhere('LOWER(p.name) LIKE :search')
                    ->setParameter('search', '%' . strtolower($request->query->get('search')) . '%');
            }

            // Sort
            $sort = $request->query->get('sort', 'createdAt');
            $order = $request->query->get('order', 'DESC');
            $qb->orderBy('p.' . $sort, $order);

            // Pagination
            $page = (int) $request->query->get('page', 1);
            $limit = (int) $request->query->get('limit', 20);
            $offset = ($page - 1) * $limit;

            $qb->setMaxResults($limit)
                ->setFirstResult($offset);
        }

        $products = $qb->getQuery()->getResult();

        // Apply locale-specific translations
        $localizedProducts = [];
        foreach ($products as $product) {
            $localizedProduct = $this->applyTranslations($product, $locale, $tenantIdVo);
            $localizedProducts[] = $localizedProduct;
        }

        // Warm cache for retrieved products
        $this->warmCache($localizedProducts, $locale, $tenantIdVo);

        return $localizedProducts;
    }

    /**
     * Resolve locale from request
     */
    private function resolveLocale($request): Locale
    {
        // Priority 1: Query parameter
        if ($request && $request->query->has('locale')) {
            try {
                return Locale::normalize($request->query->get('locale'));
            } catch (\InvalidArgumentException) {
                // Fall through to next priority
            }
        }

        // Priority 2: Accept-Language header
        if ($request && $request->headers->has('Accept-Language')) {
            $acceptLanguage = $request->headers->get('Accept-Language', '');
            $locales = LocalizationPolicy::parseAcceptLanguageHeader($acceptLanguage);

            if (!empty($locales)) {
                return $locales[0]; // Use highest priority locale
            }
        }

        // Priority 3: Default locale
        return Locale::fromString('en_US');
    }

    /**
     * Apply translations to product based on locale
     */
    private function applyTranslations(
        ProductEntityWithTranslations $product,
        Locale $locale,
        TenantId $tenantId
    ): array {
        // Convert to array representation with localized fields
        $productArray = [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getLocalizedName($locale->toString()),
            'description' => $product->getLocalizedDescription($locale->toString()),
            'shortDescription' => $product->getLocalizedShortDescription($locale->toString()),
            'slug' => $product->getSlug(),
            'price' => [
                'amount' => $product->getPriceAmount() / 100, // Convert to major units for display
                'currency' => $product->getPriceCurrency(),
                'formatted' => $this->formatPrice($product->getPriceAmount(), $product->getPriceCurrency())
            ],
            'categoryId' => $product->getCategoryId(),
            'stock' => [
                'quantity' => $product->getStockQuantity(),
                'trackInventory' => $product->isTrackInventory(),
                'allowBackorder' => $product->isAllowBackorder(),
                'inStock' => $product->getStockQuantity() > 0 || $product->isAllowBackorder()
            ],
            'images' => $product->getImages(),
            'active' => $product->isActive(),
            'featured' => $product->isFeatured(),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
            '_locale' => $locale->toString(),
            '_translations' => [
                'name' => $product->getNameTranslations(),
                'description' => $product->getDescriptionTranslations(),
                'shortDescription' => $product->getShortDescriptionTranslations()
            ]
        ];

        return $productArray;
    }

    /**
     * Format price for display
     */
    private function formatPrice(int $amountInMinorUnits, string $currencyCode): string
    {
        $amount = $amountInMinorUnits / 100;

        // Simple formatting - in production use proper money formatter
        return match($currencyCode) {
            'USD' => '$' . number_format($amount, 2),
            'EUR' => '€' . number_format($amount, 2),
            'GBP' => '£' . number_format($amount, 2),
            'RON' => number_format($amount, 2) . ' RON',
            default => $currencyCode . ' ' . number_format($amount, 2)
        };
    }

    /**
     * Warm cache for products
     */
    private function warmCache(array $products, Locale $locale, TenantId $tenantId): void
    {
        foreach ($products as $product) {
            if (!isset($product['id'])) {
                continue;
            }

            // Cache the translations for this product and locale
            $this->cacheService->setProductTranslations(
                $tenantId,
                \App\Catalog\Domain\Model\ProductId::fromString($product['id']),
                $locale,
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'shortDescription' => $product['shortDescription']
                ]
            );
        }
    }
}