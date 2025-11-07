<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function __invoke(CreateCategory $command): void
    {
        // Business rule: Validate parent category exists if specified
        if (null !== $command->parentId) {
            $parent = $this->categoryRepository->findById($command->parentId);
            if (null === $parent) {
                throw new \DomainException('Parent category not found');
            }

            // Verify parent belongs to same tenant
            if (!$parent->tenantId()->equals($command->tenantId)) {
                throw new \DomainException('Parent category must belong to same tenant');
            }
        }

        $category = Category::create(
            id: $command->id,
            tenantId: $command->tenantId,
            name: $command->name,
            description: $command->description,
            parentId: $command->parentId,
            position: $command->position,
            showOnFront: $command->showOnFront
        );

        $this->categoryRepository->save($category);
    }
}
