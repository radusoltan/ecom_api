<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCategoriesHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    /**
     * @return \App\Catalog\Domain\Model\Category[]
     */
    public function __invoke(GetCategories $query): array
    {
        return $this->categoryRepository->findByTenant($query->tenantId);
    }
}
