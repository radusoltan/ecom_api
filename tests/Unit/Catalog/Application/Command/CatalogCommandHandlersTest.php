<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\UpdateCategory;
use App\Catalog\Application\Command\UpdateCategoryHandler;
use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateCategoryHandler::class)]
final class CatalogCommandHandlersTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // ---------------------------------------------------------------
    // UpdateCategoryHandler
    // ---------------------------------------------------------------

    #[Test]
    public function updateCategoryUpdatesAndSaves(): void
    {
        $categoryId = CategoryId::generate();
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($this->tenantId);
        $category->expects(self::once())->method('update');

        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->method('findById')->with($categoryId)->willReturn($category);
        $repo->expects(self::once())->method('save')->with($category);

        $handler = new UpdateCategoryHandler($repo);
        $handler(new UpdateCategory(
            id: $categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Updated'),
            description: 'desc',
            parentId: null,
            position: 1,
            showOnFront: true,
        ));
    }

    #[Test]
    public function updateCategoryThrowsWhenNotFound(): void
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new UpdateCategoryHandler($repo);

        $this->expectException(CategoryNotFoundException::class);
        $handler(new UpdateCategory(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Test'),
            description: null,
            parentId: null,
            position: 0,
            showOnFront: false,
        ));
    }

    #[Test]
    public function updateCategoryThrowsWhenTenantMismatch(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn(TenantId::fromString('00000000-0000-4000-8000-000000000002'));

        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->method('findById')->willReturn($category);

        $handler = new UpdateCategoryHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot update category from different tenant');
        $handler(new UpdateCategory(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Test'),
            description: null,
            parentId: null,
            position: 0,
            showOnFront: false,
        ));
    }

    #[Test]
    public function updateCategoryThrowsOnSelfParent(): void
    {
        $categoryId = CategoryId::generate();
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($this->tenantId);

        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->method('findById')->willReturn($category);

        $handler = new UpdateCategoryHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Category cannot be its own parent');
        $handler(new UpdateCategory(
            id: $categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Test'),
            description: null,
            parentId: $categoryId,
            position: 0,
            showOnFront: false,
        ));
    }
}
