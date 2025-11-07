<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CategoryEntityTest extends TestCase
{
    private CategoryId $categoryId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->categoryId = CategoryId::generate();
        $this->tenantId = TenantId::generate();
    }

    public function testFromDomainModelConvertsCategoryCorrectly(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Electronics'),
            description: 'Electronic products',
            parentId: null,
            position: 1,
            showOnFront: true
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertSame($this->categoryId->toString(), $entity->getId());
        $this->assertSame($this->tenantId->toString(), $entity->getTenantId());
        $this->assertSame('Electronics', $entity->getName());
        $this->assertSame('Electronic products', $entity->getDescription());
        $this->assertSame('electronics', $entity->getSlug());
        $this->assertNull($entity->getParentId());
        $this->assertSame(1, $entity->getPosition());
        $this->assertTrue($entity->isActive());
        $this->assertTrue($entity->isShowOnFront());
        $this->assertNull($entity->getCoverImage());
    }

    public function testFromDomainModelWithParentCategory(): void
    {
        $parentId = CategoryId::generate();
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Laptops'),
            description: 'Laptop computers',
            parentId: $parentId,
            position: 2,
            showOnFront: false
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertSame($parentId->toString(), $entity->getParentId());
        $this->assertSame(2, $entity->getPosition());
    }

    public function testToDomainModelReconstitutiesCategoryCorrectly(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Books'),
            description: 'Book collection',
            parentId: null,
            position: 0
        );

        $category->assignCoverImage('/media/originals/books.jpg');

        $entity = CategoryEntity::fromDomainModel($category);
        $reconstituted = $entity->toDomainModel();

        $this->assertTrue($reconstituted->id()->equals($this->categoryId));
        $this->assertTrue($reconstituted->tenantId()->equals($this->tenantId));
        $this->assertSame('Books', $reconstituted->name()->value());
        $this->assertSame('Book collection', $reconstituted->description());
        $this->assertSame('books', $reconstituted->slug()->value());
        $this->assertNull($reconstituted->parentId());
        $this->assertSame(0, $reconstituted->position());
        $this->assertTrue($reconstituted->isActive());
        $this->assertFalse($reconstituted->showOnFront());
        $this->assertSame('/media/originals/books.jpg', $reconstituted->coverImage());
    }

    public function testToDomainModelWithParentId(): void
    {
        $parentId = CategoryId::generate();

        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Subcategory'),
            description: 'Child category',
            parentId: $parentId,
            position: 5,
            showOnFront: true
        );

        $category->deactivate();

        $entity = CategoryEntity::fromDomainModel($category);
        $reconstituted = $entity->toDomainModel();

        $this->assertNotNull($reconstituted->parentId());
        $this->assertTrue($reconstituted->parentId()->equals($parentId));
        $this->assertFalse($reconstituted->isActive());
        $this->assertSame(5, $reconstituted->position());
        $this->assertTrue($reconstituted->showOnFront());
    }

    public function testSettersWorkCorrectly(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Original Category'),
            description: 'Original description',
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        // Test setters
        $entity->setTenantId('new-tenant-id');
        $this->assertSame('new-tenant-id', $entity->getTenantId());

        $entity->setName('New Category Name');
        $this->assertSame('New Category Name', $entity->getName());

        $entity->setDescription('New description');
        $this->assertSame('New description', $entity->getDescription());

        $newParentId = CategoryId::generate();
        $entity->setParentId($newParentId->toString());
        $this->assertSame($newParentId->toString(), $entity->getParentId());

        $entity->setPosition(10);
        $this->assertSame(10, $entity->getPosition());

        $entity->setActive(false);
        $this->assertFalse($entity->isActive());

        $entity->setActive(true);
        $this->assertTrue($entity->isActive());

        $entity->setShowOnFront(true);
        $this->assertTrue($entity->isShowOnFront());

        $entity->setShowOnFront(false);
        $this->assertFalse($entity->isShowOnFront());

        $entity->setCoverImage('/media/originals/cover.jpg');
        $this->assertSame('/media/originals/cover.jpg', $entity->getCoverImage());
    }

    public function testParentIdCanBeNullAndSet(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Root Category'),
            description: 'Top level',
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertNull($entity->getParentId());

        // Can set parent
        $parentId = CategoryId::generate();
        $entity->setParentId($parentId->toString());
        $this->assertSame($parentId->toString(), $entity->getParentId());

        // Can set back to null
        $entity->setParentId(null);
        $this->assertNull($entity->getParentId());
    }

    public function testCreatedAtAndUpdatedAtArePreserved(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Date Test'),
            description: 'Testing dates',
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        $reconstituted = $entity->toDomainModel();

        $this->assertEquals($category->createdAt(), $reconstituted->createdAt());
        $this->assertEquals($category->updatedAt(), $reconstituted->updatedAt());
    }

    public function testInactiveCategoryConversion(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Inactive'),
            description: 'Inactive category',
            parentId: null,
            position: 0
        );

        $category->deactivate();

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertFalse($entity->isActive());

        $reconstituted = $entity->toDomainModel();
        $this->assertFalse($reconstituted->isActive());
    }

    public function testCategoryWithNullDescription(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('No Description'),
            description: null,
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertNull($entity->getDescription());

        $reconstituted = $entity->toDomainModel();
        $this->assertNull($reconstituted->description());
    }

    public function testCategoryNamePreservesCase(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('CamelCase Category'),
            description: 'Case test',
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertSame('CamelCase Category', $entity->getName());
    }

    public function testSlugIsGeneratedFromName(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Test Category Slug'),
            description: 'Slug test',
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertSame('test-category-slug', $entity->getSlug());
    }

    public function testPositionHandling(): void
    {
        $category1 = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('First'),
            description: 'First category',
            parentId: null,
            position: 1
        );

        $entity1 = CategoryEntity::fromDomainModel($category1);
        $this->assertSame(1, $entity1->getPosition());

        $category2 = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Second'),
            description: 'Second category',
            parentId: null,
            position: 2
        );

        $entity2 = CategoryEntity::fromDomainModel($category2);
        $this->assertSame(2, $entity2->getPosition());

        // Position can be changed
        $entity2->setPosition(10);
        $this->assertSame(10, $entity2->getPosition());
    }

    public function testRoundTripConversionPreservesAllData(): void
    {
        $parentId = CategoryId::generate();

        $originalCategory = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Round Trip Test'),
            description: 'Testing round trip conversion',
            parentId: $parentId,
            position: 42
        );

        // Convert to entity
        $entity = CategoryEntity::fromDomainModel($originalCategory);

        // Convert back to domain
        $reconstituted = $entity->toDomainModel();

        // Verify all properties match
        $this->assertTrue($originalCategory->id()->equals($reconstituted->id()));
        $this->assertTrue($originalCategory->tenantId()->equals($reconstituted->tenantId()));
        $this->assertSame($originalCategory->name()->value(), $reconstituted->name()->value());
        $this->assertSame($originalCategory->description(), $reconstituted->description());
        $this->assertSame($originalCategory->slug()->value(), $reconstituted->slug()->value());
        $this->assertTrue($originalCategory->parentId()->equals($reconstituted->parentId()));
        $this->assertSame($originalCategory->position(), $reconstituted->position());
        $this->assertSame($originalCategory->isActive(), $reconstituted->isActive());
    }

    public function testSetTranslatableLocale(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Locale Test'),
            description: 'Testing locale',
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        // This method should not throw an error
        $entity->setTranslatableLocale('en');
        $entity->setTranslatableLocale('fr');
        $entity->setTranslatableLocale(null);

        $this->assertTrue(true); // If we got here, the method works
    }

    public function testEmptyDescriptionHandling(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Empty Desc'),
            description: '',
            parentId: null,
            position: 0
        );

        $entity = CategoryEntity::fromDomainModel($category);

        $this->assertSame('', $entity->getDescription());

        $reconstituted = $entity->toDomainModel();
        $this->assertSame('', $reconstituted->description());
    }

    public function testCategoryActivationDeactivation(): void
    {
        $category = Category::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Toggle Active'),
            description: 'Testing active toggle',
            parentId: null,
            position: 0
        );

        // Initially active
        $entity1 = CategoryEntity::fromDomainModel($category);
        $this->assertTrue($entity1->isActive());

        // Deactivate
        $category->deactivate();
        $entity2 = CategoryEntity::fromDomainModel($category);
        $this->assertFalse($entity2->isActive());

        // Activate again
        $category->activate();
        $entity3 = CategoryEntity::fromDomainModel($category);
        $this->assertTrue($entity3->isActive());
    }
}
