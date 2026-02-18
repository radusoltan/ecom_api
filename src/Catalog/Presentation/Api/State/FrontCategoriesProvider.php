<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\DTO\StorefrontCategoryDto;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class FrontCategoriesProvider implements ProviderInterface
{
    private const CACHE_KEY_PREFIX = 'sf:homecat';
    private const CACHE_TTL = 300; // 5 minutes
    private const DEFAULT_LIMIT = 12;

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

        // Fetch categories marked as showOnFront using QueryBuilder
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c')
            ->from('App\\Catalog\\Infrastructure\\Persistence\\Doctrine\\Entity\\CategoryEntity', 'c')
            ->where('c.tenantId = :tenantId')
            ->andWhere('c.showOnFront = :showOnFront')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('showOnFront', true)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        // Disable Gedmo Translatable to avoid GROUP BY issues
        $query = $qb->getQuery();
        $query->setHint(
            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
            \Gedmo\Translatable\Query\TreeWalker\TranslationWalker::class
        );
        $query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

        $categoryEntities = $query->getResult();

        // Map to DTOs
        $dtos = [];
        foreach ($categoryEntities as $entity) {
            $category = $entity->toDomainModel();
            $dtos[] = $this->mapToDto($category, $locale);
        }

        // Cache the result
        if ($this->cache && isset($cacheKey) && isset($item)) {
            $item->set($dtos);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }

        return $dtos;
    }

    private function mapToDto($category, string $locale): StorefrontCategoryDto
    {
        $image = null;
        $coverImage = $category->coverImage();

        if ($coverImage) {
            $image = [
                'urlSm' => $coverImage,
                'urlMd' => $coverImage,
                'urlLg' => $coverImage,
            ];
        }

        // Count children categories
        $childrenCount = $category->children() ? count($category->children()) : 0;

        return new StorefrontCategoryDto(
            id: $category->id()->toString(),
            slug: $category->slug() ?? $category->id()->toString(),
            name: $category->name()->value(),
            image: $image,
            showOnFront: $category->showOnFront(),
            childrenCount: $childrenCount,
            description: $category->description() ?? null
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
