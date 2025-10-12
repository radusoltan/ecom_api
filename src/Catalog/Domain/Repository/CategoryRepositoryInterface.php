<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Slug;
use App\Shared\Domain\ValueObject\TenantId;

interface CategoryRepositoryInterface
{
    public function save(Category $category): void;

    public function findById(CategoryId $id): ?Category;

    public function findBySlug(TenantId $tenantId, Slug $slug): ?Category;

    /**
     * @return Category[]
     */
    public function findByTenant(TenantId $tenantId): array;

    /**
     * @return Category[]
     */
    public function findByParent(TenantId $tenantId, ?CategoryId $parentId): array;

    public function delete(CategoryId $id): void;
}
