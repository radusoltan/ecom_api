<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider for ProductEntity collection operations
 * Filters products by tenant_id from X-Tenant-ID header
 * Supports Gedmo Translatable for automatic product translation
 *
 * @implements ProviderInterface<ProductEntity>
 */
final readonly class ProductCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get tenant ID from context (injected by TenantContextProvider decorator)
        $tenantId = $context['tenant_id'] ?? null;

        if ($tenantId === null) {
            throw new \RuntimeException('Tenant ID is required but not provided in X-Tenant-ID header');
        }

        // Get current request to extract locale
        $request = $this->requestStack->getCurrentRequest();

        // Get locale from query parameter or Accept-Language header
        $locale = $request?->query->get('locale')
            ?? $request?->getPreferredLanguage(['en', 'fr', 'de'])
            ?? 'en';

        // Build query with Gedmo Translatable support
        $query = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(ProductEntity::class, 'p')
            ->where('p.tenantId = :tenantId')
            ->andWhere('p.active = :active')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery();

        // Set Gedmo Translatable hints to load translations
        $query->setHint(
            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
        );
        $query->setHint('Gedmo.translatable.locale', $locale);

        return $query->getResult();
    }
}
