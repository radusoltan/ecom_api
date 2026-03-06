<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\CreateCategory;
use App\Catalog\Application\Command\CreateCategoryHandler;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateCategoryHandler::class)]
final class CreateCategoryHandlerTest extends TestCase
{
    private CategoryRepositoryInterface $categoryRepository;
    private CreateCategoryHandler $handler;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->handler = new CreateCategoryHandler($this->categoryRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesRootCategoryWithoutParent(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $categoryId = CategoryId::generate();
        $command = new CreateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Electronics'),
            description: 'All electronics',
            parentId: null,
            position: 0,
            showOnFront: true,
        );

        $this->categoryRepository->expects(self::never())->method('findById');
        $this->categoryRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Category::class));

        ($this->handler)($command);
    }

    #[Test]
    public function itCreatesCategoryWithValidParent(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $parentId = CategoryId::generate();
        $categoryId = CategoryId::generate();
        $command = new CreateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Smartphones'),
            description: null,
            parentId: $parentId,
            position: 1,
        );

        $parentCategory = Category::create(
            id: $parentId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Electronics'),
            description: null,
            parentId: null,
        );

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->with($parentId)
            ->willReturn($parentCategory);

        $this->categoryRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Category::class));

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------
    // Error paths
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenParentCategoryNotFound(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $parentId = CategoryId::generate();
        $command = new CreateCategory(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Orphan'),
            description: null,
            parentId: $parentId,
        );

        $this->categoryRepository->method('findById')->willReturn(null);
        $this->categoryRepository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Parent category not found');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenParentBelongsToDifferentTenant(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $otherTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000002');
        $parentId = CategoryId::generate();
        $command = new CreateCategory(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Child'),
            description: null,
            parentId: $parentId,
        );

        $parentCategory = Category::create(
            id: $parentId,
            tenantId: $otherTenantId,
            name: CategoryName::fromString('Other Tenant Root'),
            description: null,
            parentId: null,
        );

        $this->categoryRepository
            ->method('findById')
            ->willReturn($parentCategory);

        $this->categoryRepository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Parent category must belong to same tenant');

        ($this->handler)($command);
    }
}
