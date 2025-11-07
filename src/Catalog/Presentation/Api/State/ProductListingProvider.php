<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\DTO\StorefrontProductDto;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProductListingProvider implements ProviderInterface
{
    private const CACHE_KEY_PREFIX = 'sf:list';
    private const CACHE_TTL = 120; // 2 minutes
    private const DEFAULT_ITEMS_PER_PAGE = 24;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
        private readonly RequestStack $requestStack,
        private readonly Pagination $pagination,
        private readonly ?CacheItemPoolInterface $cache = null
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
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

        // Generate cache key based on filters
        $cacheKey = $this->generateCacheKey($tenantId, $locale, $filters, $page, $itemsPerPage);

        // Try to get from cache
        if ($this->cache) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }
        }

        // Build query with filters
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from('App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity', 'p')
            ->where('p.tenantId = :tenantId')
            ->andWhere('p.active = :active')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('active', true);

        // Apply filters
        if (!empty($filters['q'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%'.$filters['q'].'%');
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('p.categoryId = :categoryId')
                ->setParameter('categoryId', $filters['category']);
        }

        if (isset($filters['priceMin'])) {
            $qb->andWhere('p.priceAmount >= :priceMin')
                ->setParameter('priceMin', $filters['priceMin']);
        }

        if (isset($filters['priceMax'])) {
            $qb->andWhere('p.priceAmount <= :priceMax')
                ->setParameter('priceMax', $filters['priceMax']);
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'price_asc':
                $qb->orderBy('p.priceAmount', 'ASC');

                break;
            case 'price_desc':
                $qb->orderBy('p.priceAmount', 'DESC');

                break;
            case 'name':
                $qb->orderBy('p.name', 'ASC');

                break;
            case 'newest':
            default:
                $qb->orderBy('p.createdAt', 'DESC');

                break;
        }

        // Get total count BEFORE adding pagination (avoid GROUP BY issues)
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT p.id)')
            ->resetDQLPart('orderBy')
            ->setFirstResult(0)
            ->setMaxResults(null);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply pagination
        $offset = ($page - 1) * $itemsPerPage;
        $qb->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        // Execute query
        $query = $qb->getQuery();
        $productEntities = $query->getResult();

        // Map to DTOs
        $products = [];
        foreach ($productEntities as $entity) {
            try {
                $product = $entity->toDomainModel();
                $products[] = $this->mapToDto($product, $locale);
            } catch (\Exception $e) {
                // Skip products that fail conversion
                error_log('[ProductListingProvider] Failed to convert entity '.$entity->getId().': '.$e->getMessage());

                continue;
            }
        }

        // Cache the result
        if ($this->cache && isset($item)) {
            $item->set($products);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }

        // Return simple array of products - API Platform will handle collection wrapping
        return $products;
    }

    private function parseFilters($request): array
    {
        return [
            'q' => $request->query->get('q'),
            'category' => $request->query->get('category'),
            'priceMin' => $request->query->get('priceMin'),
            'priceMax' => $request->query->get('priceMax'),
            'sort' => $request->query->get('sort', 'newest'),
            'attributes' => $request->query->all('attributes') ?? [],
        ];
    }

    private function generateCacheKey(string $tenantId, string $locale, array $filters, int $page, int $itemsPerPage): string
    {
        $filterHash = md5(json_encode($filters));

        return sprintf(
            '%s:%s:%s:%s:%d:%d',
            self::CACHE_KEY_PREFIX,
            $tenantId,
            $locale,
            $filterHash,
            $page,
            $itemsPerPage
        );
    }

    private function mapToDto($product, string $locale): StorefrontProductDto
    {
        $primaryImage = null;
        $images = $product->images();

        if (!empty($images)) {
            $firstImage = $images[0];
            $primaryImage = [
                'urlSm' => $firstImage->url() ?? '',
                'urlMd' => $firstImage->url() ?? '',
                'urlLg' => $firstImage->url() ?? '',
            ];
        }

        return new StorefrontProductDto(
            id: $product->id()->toString(),
            slug: $product->slug()?->value() ?? $product->id()->toString(),
            name: $product->name()->value(),
            price: [
                'amount' => $product->price()->getAmount(),
                'currency' => $product->price()->getCurrency()->getCurrencyCode(),
            ],
            primaryImage: $primaryImage,
            isFeatured: $product->isFeatured(),
            rating: null,
            availability: $product->isActive() ? 'in_stock' : 'out_of_stock',
            breadcrumbs: null,
            description: $product->description() ?? null
        );
    }

    private function buildFacets(string $tenantId, array $filters): array
    {
        // TODO: Implement facets aggregation
        // This would typically query for available filters based on current results
        // For now, return empty array
        return [
            'categories' => [],
            'priceRanges' => [],
            'attributes' => [],
        ];
    }

    private function parseLocale(string $acceptLanguage): string
    {
        $locales = explode(',', $acceptLanguage);
        if (empty($locales)) {
            return 'en';
        }

        $locale = explode(';', $locales[0])[0];

        return strtolower(trim($locale));
    }
}
