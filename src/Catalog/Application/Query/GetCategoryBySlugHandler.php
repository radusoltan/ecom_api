<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCategoryBySlugHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function __invoke(GetCategoryBySlug $query): Category
    {
        $category = $this->categoryRepository->findBySlug($query->tenantId, $query->slug);

        if (null === $category) {
            throw CategoryNotFoundException::withSlug($query->slug);
        }

        return $category;
    }
}
