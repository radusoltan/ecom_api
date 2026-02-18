<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\DTO\StorefrontProductDto;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class FeaturedProductsProvider implements ProviderInterface
{
    private const CACHE_KEY_PREFIX = 'sf:feat';
    private const CACHE_TTL = 300; // 5 minutes
    private const DEFAULT_LIMIT = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
        private readonly RequestStack $requestStack,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return [];
        }

        $tenantId = $this->tenantContext->getTenantId();
        if (!$tenantId) {
            return [];
        }

        // Get locale from request
        $locale = $request->headers->get('Accept-Language', 'en');
        $locale = $this->parseLocale($locale);

        $limit = (int) $request->query->get('limit', self::DEFAULT_LIMIT);
        $limit = min($limit, 20); // Max 20 items

        // Try to get from cache
        if ($this->cache) {
            $cacheKey = sprintf('%s:%s:%s:%d', self::CACHE_KEY_PREFIX, $tenantId, $locale, $limit);
            $item = $this->cache->getItem($cacheKey);

            if ($item->isHit()) {
                return $item->get();
            }
        }

        // Fetch featured products using QueryBuilder
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from('App\\Catalog\\Infrastructure\\Persistence\\Doctrine\\Entity\\ProductEntity', 'p')
            ->where('p.tenantId = :tenantId')
            ->andWhere('p.isFeatured = :featured')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('featured', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        // Disable Gedmo Translatable to avoid GROUP BY issues
        $query = $qb->getQuery();
        $query->setHint(
            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
            \Gedmo\Translatable\Query\TreeWalker\TranslationWalker::class
        );
        $query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

        $productEntities = $query->getResult();

        // Map to DTOs
        $dtos = [];
        foreach ($productEntities as $entity) {
            $product = $entity->toDomainModel();
            $dtos[] = $this->mapToDto($product, $locale);
        }

        // Cache the result
        if ($this->cache && isset($cacheKey) && isset($item)) {
            $item->set($dtos);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }

        return $dtos;
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
            rating: null, // TODO: Implement rating system
            availability: $product->isActive() ? 'in_stock' : 'out_of_stock',
            breadcrumbs: null,
            description: $product->description() ?? null
        );
    }

    private function parseLocale(string $acceptLanguage): string
    {
        // Parse Accept-Language header (e.g., "en-US,en;q=0.9")
        $locales = explode(',', $acceptLanguage);
        if (empty($locales)) {
            return 'en';
        }

        $locale = explode(';', $locales[0])[0];

        return strtolower(trim($locale));
    }
}
