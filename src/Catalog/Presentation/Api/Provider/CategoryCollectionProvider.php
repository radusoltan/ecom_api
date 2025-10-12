<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CategoryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get tenant ID from header
        $tenantId = $request?->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return [];
        }

        // Get locale from Accept-Language header or query parameter
        $locale = $request?->query->get('locale')
            ?? $request?->getPreferredLanguage(['en', 'fr', 'de'])
            ?? 'en';

        // Set the translatable locale
        $query = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(CategoryEntity::class, 'c')
            ->where('c.tenantId = :tenantId')
            ->andWhere('c.active = :active')
            ->andWhere('c.parentId IS NULL') // Root categories only
            ->setParameter('tenantId', $tenantId)
            ->setParameter('active', true)
            ->orderBy('c.position', 'ASC')
            ->getQuery();

        // Set translation hint for Gedmo Translatable
        $query->setHint(
            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
        );
        $query->setHint('Gedmo.translatable.locale', $locale);

        return $query->getResult();
    }
}
