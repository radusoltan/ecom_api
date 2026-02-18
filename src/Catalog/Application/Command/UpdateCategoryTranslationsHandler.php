<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\CategoryWithTranslations;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Cache\I18nCacheService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCategoryTranslationsHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private I18nCacheService $cacheService,
    ) {
    }

    public function __invoke(UpdateCategoryTranslations $command): void
    {
        // Find the category
        $category = $this->categoryRepository->findById($command->categoryId);

        if (null === $category) {
            throw new \DomainException(sprintf('Category with ID %s not found', $command->categoryId->toString()));
        }

        // Verify tenant
        if (!$category->tenantId()->equals($command->tenantId)) {
            throw new \DomainException('Category does not belong to the specified tenant');
        }

        // Convert to CategoryWithTranslations if needed
        if (!$category instanceof CategoryWithTranslations) {
            // This would be handled by a migration/adapter in a real implementation
            throw new \DomainException('Category does not support translations yet');
        }

        // Update translations
        $category->updateTranslations(
            $command->locale,
            $command->name,
            $command->description
        );

        // Save the category (will dispatch domain events)
        $this->categoryRepository->save($category);

        // Invalidate cache for this category and locale
        $this->cacheService->invalidateCategory(
            $command->tenantId,
            $command->categoryId,
            $command->locale
        );
    }
}
