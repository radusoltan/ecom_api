<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testCreateCategorySucceeds(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $name = CategoryName::fromString('Electronics');
        $description = 'Electronic products';

        $category = Category::create(
            id: $categoryId,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            parentId: null,
            position: 0
        );

        $this->assertTrue($category->id()->equals($categoryId));
        $this->assertTrue($category->tenantId()->equals($tenantId));
        $this->assertSame('Electronics', $category->name()->value());
        $this->assertSame('Electronic products', $category->description());
        $this->assertSame('electronics', $category->slug()->value());
        $this->assertNull($category->parentId());
        $this->assertSame(0, $category->position());
        $this->assertTrue($category->isActive());
        $this->assertFalse($category->showOnFront());
        $this->assertNull($category->coverImage());
    }

    public function testCategorySlugGeneratedFromName(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Home & Garden'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->assertSame('home-garden', $category->slug()->value());
    }

    public function testCreateCategoryWithParent(): void
    {
        $parentId = CategoryId::generate();

        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Laptops'),
            description: 'Laptop computers',
            parentId: $parentId,
            position: 1,
            showOnFront: true,
            coverImage: '/media/originals/laptops.jpg'
        );

        $this->assertNotNull($category->parentId());
        $this->assertTrue($category->parentId()->equals($parentId));
        $this->assertSame(1, $category->position());
        $this->assertTrue($category->showOnFront());
        $this->assertSame('/media/originals/laptops.jpg', $category->coverImage());
    }

    public function testUpdateCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Original Name'),
            description: 'Original description',
            parentId: null,
            position: 0,
            showOnFront: false
        );

        $originalUpdatedAt = $category->updatedAt();

        sleep(1);

        $newParentId = CategoryId::generate();

        $category->update(
            name: CategoryName::fromString('Updated Name'),
            description: 'Updated description',
            parentId: $newParentId,
            position: 5,
            showOnFront: true
        );

        $this->assertSame('Updated Name', $category->name()->value());
        $this->assertSame('Updated description', $category->description());
        $this->assertNotNull($category->parentId());
        $this->assertTrue($category->parentId()->equals($newParentId));
        $this->assertSame(5, $category->position());
        $this->assertTrue($category->showOnFront());
        $this->assertGreaterThan($originalUpdatedAt, $category->updatedAt());
    }

    public function testDeactivateCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Category to Deactivate'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->assertTrue($category->isActive());

        $category->deactivate();

        $this->assertFalse($category->isActive());
    }

    public function testActivateCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Category to Activate'),
            description: null,
            parentId: null,
            position: 0
        );

        $category->deactivate();
        $this->assertFalse($category->isActive());

        $category->activate();

        $this->assertTrue($category->isActive());
    }

    public function testCreateRootCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Root Category'),
            description: 'Top-level category',
            parentId: null,
            position: 0
        );

        $this->assertNull($category->parentId());
    }

    public function testCreateSubcategory(): void
    {
        $parentId = CategoryId::generate();

        $subcategory = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Subcategory'),
            description: 'Child category',
            parentId: $parentId,
            position: 0
        );

        $this->assertNotNull($subcategory->parentId());
        $this->assertTrue($subcategory->parentId()->equals($parentId));
    }

    public function testUpdateCategoryRemovesParent(): void
    {
        $parentId = CategoryId::generate();

        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Subcategory'),
            description: null,
            parentId: $parentId,
            position: 0
        );

        $this->assertNotNull($category->parentId());

        $category->update(
            name: CategoryName::fromString('Now Root Category'),
            description: null,
            parentId: null,
            position: 0,
            showOnFront: false
        );

        $this->assertNull($category->parentId());
    }

    public function testAssignAndRemoveCoverImage(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Media Category'),
            description: null,
            parentId: null,
            position: 0
        );

        $category->assignCoverImage('/media/originals/media.jpg');

        $this->assertSame('/media/originals/media.jpg', $category->coverImage());

        $category->removeCoverImage();

        $this->assertNull($category->coverImage());
    }

    public function testCategoryPositionOrdering(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $category1 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('First Category'),
            description: null,
            parentId: null,
            position: 0
        );

        $category2 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Second Category'),
            description: null,
            parentId: null,
            position: 1
        );

        $category3 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Third Category'),
            description: null,
            parentId: null,
            position: 2
        );

        $this->assertSame(0, $category1->position());
        $this->assertSame(1, $category2->position());
        $this->assertSame(2, $category3->position());
        $this->assertLessThan($category2->position(), $category1->position());
        $this->assertLessThan($category3->position(), $category2->position());
    }

    public function testUpdateCategoryDescription(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Category'),
            description: 'Original description',
            parentId: null,
            position: 0
        );

        $category->update(
            name: CategoryName::fromString('Category'),
            description: 'New description',
            parentId: null,
            position: 0,
            showOnFront: false
        );

        $this->assertSame('New description', $category->description());
    }

    public function testCreateCategoryWithNullDescription(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Category Without Description'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->assertNull($category->description());
    }

    public function testCategoryTimestamps(): void
    {
        $beforeCreation = new \DateTimeImmutable();

        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Timestamped Category'),
            description: null,
            parentId: null,
            position: 0
        );

        $afterCreation = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreation, $category->createdAt());
        $this->assertLessThanOrEqual($afterCreation, $category->createdAt());
        // Check that timestamps are within 1 second of each other
        $this->assertLessThanOrEqual(1, abs($category->createdAt()->getTimestamp() - $category->updatedAt()->getTimestamp()));
    }
}
