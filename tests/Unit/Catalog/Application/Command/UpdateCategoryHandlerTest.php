<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\UpdateCategory;
use App\Catalog\Application\Command\UpdateCategoryHandler;
use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateCategoryHandler::class)]
final class UpdateCategoryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CategoryRepositoryInterface $categoryRepository;
    private UpdateCategoryHandler $handler;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->handler = new UpdateCategoryHandler($this->categoryRepository);
    }

    // -----------------------------------------------------------------------
    // Happy path — updates category and saves
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesCategoryAndSaves(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $categoryId = CategoryId::generate();

        $category = Category::reconstituteFromPersistence(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Old Name'),
            description: 'Old description',
            slug: Slug::fromString('old-name'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        $this->categoryRepository
            ->expects(self::once())
            ->method('save')
            ->with($category);

        $command = new UpdateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('New Name'),
            description: 'Updated description',
            parentId: null,
            position: 5,
            showOnFront: true,
        );

        ($this->handler)($command);

        self::assertSame('New Name', $category->name()->value());
        self::assertSame('Updated description', $category->description());
        self::assertSame(5, $category->position());
        self::assertTrue($category->showOnFront());
    }

    // -----------------------------------------------------------------------
    // Throws when category is not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCategoryNotFound(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $categoryId = CategoryId::generate();

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn(null);

        $this->categoryRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(CategoryNotFoundException::class);

        ($this->handler)(new UpdateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Any Name'),
            description: null,
            parentId: null,
            position: 0,
            showOnFront: false,
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when category belongs to different tenant
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCategoryBelongsToDifferentTenant(): void
    {
        $requestTenantId = TenantId::fromString(self::TENANT_ID);
        $differentTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000002');
        $categoryId = CategoryId::generate();

        $category = Category::reconstituteFromPersistence(
            id: $categoryId,
            tenantId: $differentTenantId,
            name: CategoryName::fromString('Other Tenant Category'),
            description: null,
            slug: Slug::fromString('other-tenant-category'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->categoryRepository
            ->method('findById')
            ->willReturn($category);

        $this->categoryRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot update category from different tenant');

        ($this->handler)(new UpdateCategory(
            id: $categoryId,
            tenantId: $requestTenantId,
            name: CategoryName::fromString('New Name'),
            description: null,
            parentId: null,
            position: 0,
            showOnFront: false,
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when category is set as its own parent (circular reference)
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCategoryIsItsOwnParent(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $categoryId = CategoryId::generate();

        $category = Category::reconstituteFromPersistence(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Self Parent'),
            description: null,
            slug: Slug::fromString('self-parent'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->categoryRepository
            ->method('findById')
            ->willReturn($category);

        $this->categoryRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Category cannot be its own parent');

        ($this->handler)(new UpdateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Self Parent'),
            description: null,
            parentId: $categoryId, // Same as the category itself
            position: 0,
            showOnFront: false,
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when parent category is not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenParentCategoryNotFound(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $categoryId = CategoryId::generate();
        $parentId = CategoryId::generate();

        $category = Category::reconstituteFromPersistence(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category'),
            description: null,
            slug: Slug::fromString('child-category'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        // First call returns the category being updated, second returns null for parent
        $callCount = 0;
        $this->categoryRepository
            ->method('findById')
            ->willReturnCallback(function (CategoryId $id) use ($category, &$callCount) {
                ++$callCount;
                if (1 === $callCount) {
                    return $category;
                }

                return null; // Parent not found
            });

        $this->categoryRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Parent category not found');

        ($this->handler)(new UpdateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category'),
            description: null,
            parentId: $parentId,
            position: 0,
            showOnFront: false,
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when parent category belongs to different tenant
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenParentCategoryBelongsToDifferentTenant(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $differentTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000002');
        $categoryId = CategoryId::generate();
        $parentId = CategoryId::generate();

        $category = Category::reconstituteFromPersistence(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category'),
            description: null,
            slug: Slug::fromString('child-category'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $parentCategory = Category::reconstituteFromPersistence(
            id: $parentId,
            tenantId: $differentTenantId, // Different tenant
            name: CategoryName::fromString('Parent Category'),
            description: null,
            slug: Slug::fromString('parent-category'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $callCount = 0;
        $this->categoryRepository
            ->method('findById')
            ->willReturnCallback(function () use ($category, $parentCategory, &$callCount) {
                ++$callCount;

                return 1 === $callCount ? $category : $parentCategory;
            });

        $this->categoryRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Parent category must belong to same tenant');

        ($this->handler)(new UpdateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category'),
            description: null,
            parentId: $parentId,
            position: 0,
            showOnFront: false,
        ));
    }

    // -----------------------------------------------------------------------
    // Successfully reparents category when valid parent exists
    // -----------------------------------------------------------------------

    #[Test]
    public function itReparentsCategoryWhenValidParentProvided(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $categoryId = CategoryId::generate();
        $parentId = CategoryId::generate();

        $category = Category::reconstituteFromPersistence(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category'),
            description: null,
            slug: Slug::fromString('child-category'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $parentCategory = Category::reconstituteFromPersistence(
            id: $parentId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Parent Category'),
            description: null,
            slug: Slug::fromString('parent-category'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $callCount = 0;
        $this->categoryRepository
            ->method('findById')
            ->willReturnCallback(function () use ($category, $parentCategory, &$callCount) {
                ++$callCount;

                return 1 === $callCount ? $category : $parentCategory;
            });

        $this->categoryRepository
            ->expects(self::once())
            ->method('save')
            ->with($category);

        ($this->handler)(new UpdateCategory(
            id: $categoryId,
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category'),
            description: null,
            parentId: $parentId,
            position: 1,
            showOnFront: false,
        ));

        self::assertTrue($category->parentId()->equals($parentId));
    }
}
