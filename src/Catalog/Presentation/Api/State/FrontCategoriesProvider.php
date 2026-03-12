<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\DTO\StorefrontCategoryDto;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class FrontCategoriesProvider implements ProviderInterface
{
    private const DEFAULT_LIMIT = 12;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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

        // Children count is not available on the domain model (Category only stores parentId).
        // A dedicated query would be needed for accurate counts; default to 0 here.
        $childrenCount = 0;

        return new StorefrontCategoryDto(
            id: $category->id()->toString(),
            slug: $category->slug()?->value() ?? $category->id()->toString(),
            name: $category->name()->value(),
            image: $image,
            showOnFront: $category->showOnFront(),
            childrenCount: $childrenCount,
            description: $category->description() ?? null
        );
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
