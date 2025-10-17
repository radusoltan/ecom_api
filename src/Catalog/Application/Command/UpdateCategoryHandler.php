<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    public function __invoke(UpdateCategory $command): void
    {
        $category = $this->categoryRepository->findById($command->id);

        if ($category === null) {
            throw CategoryNotFoundException::withId($command->id);
        }

        // Business rule: Verify tenant ownership
        if (!$category->tenantId()->equals($command->tenantId)) {
            throw new \DomainException('Cannot update category from different tenant');
        }

        // Business rule: Validate parent category if specified
        if ($command->parentId !== null) {
            // Prevent circular reference
            if ($command->parentId->equals($command->id)) {
                throw new \DomainException('Category cannot be its own parent');
            }

            $parent = $this->categoryRepository->findById($command->parentId);
            if ($parent === null) {
                throw new \DomainException('Parent category not found');
            }

            // Verify parent belongs to same tenant
            if (!$parent->tenantId()->equals($command->tenantId)) {
                throw new \DomainException('Parent category must belong to same tenant');
            }
        }

        $category->update(
            name: $command->name,
            description: $command->description,
            parentId: $command->parentId,
            position: $command->position,
            showOnFront: $command->showOnFront
        );

        $this->categoryRepository->save($category);
    }
}
