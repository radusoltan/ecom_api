<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\Slug;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Category::class)]
final class CategoryExtendedTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Domain events
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsCategoryCreatedEventOnCreate(): void
    {
        $id = CategoryId::generate();

        $category = Category::create(
            id: $id,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Electronics'),
            description: null,
            parentId: null,
        );

        $events = $category->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CategoryCreated::class, $events[0]);
        self::assertTrue($events[0]->categoryId->equals($id));
        self::assertTrue($events[0]->tenantId->equals($this->tenantId));
        self::assertSame('Electronics', $events[0]->name);
    }

    #[Test]
    public function itRecordsCategoryUpdatedEventOnUpdate(): void
    {
        $id = CategoryId::generate();

        $category = Category::create(
            id: $id,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Original'),
            description: null,
            parentId: null,
        );
        $category->popEvents();

        $category->update(
            name: CategoryName::fromString('Updated'),
            description: 'New desc',
            parentId: null,
            position: 1,
            showOnFront: true,
        );

        $events = $category->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CategoryUpdated::class, $events[0]);
        self::assertTrue($events[0]->categoryId->equals($id));
    }

    #[Test]
    public function itHasNoEventsAfterPoppingThem(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Test'),
            description: null,
            parentId: null,
        );

        $first = $category->popEvents();
        self::assertCount(1, $first);

        $second = $category->popEvents();
        self::assertCount(0, $second);
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstitutesFromPersistenceWithNoEvents(): void
    {
        $id = CategoryId::generate();
        $parentId = CategoryId::generate();
        $createdAt = new \DateTimeImmutable('2025-01-01');
        $updatedAt = new \DateTimeImmutable('2025-06-01');
        $slug = Slug::fromString('electronics');

        $category = Category::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Electronics'),
            description: 'Electronic goods',
            slug: $slug,
            parentId: $parentId,
            position: 3,
            active: false,
            showOnFront: true,
            coverImage: '/media/cat.jpg',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        // No events on reconstitution
        self::assertCount(0, $category->popEvents());

        self::assertTrue($category->id()->equals($id));
        self::assertTrue($category->tenantId()->equals($this->tenantId));
        self::assertSame('Electronics', $category->name()->value());
        self::assertSame('Electronic goods', $category->description());
        self::assertSame('electronics', $category->slug()->value());
        self::assertTrue($category->parentId()->equals($parentId));
        self::assertSame(3, $category->position());
        self::assertFalse($category->isActive());
        self::assertTrue($category->showOnFront());
        self::assertSame('/media/cat.jpg', $category->coverImage());
        self::assertSame($createdAt, $category->createdAt());
        self::assertSame($updatedAt, $category->updatedAt());
    }

    #[Test]
    public function itReconstitutesFromPersistenceWithNullOptionals(): void
    {
        $category = Category::reconstituteFromPersistence(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Root'),
            description: null,
            slug: Slug::fromString('root'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            coverImage: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        self::assertNull($category->description());
        self::assertNull($category->parentId());
        self::assertNull($category->coverImage());
        self::assertTrue($category->isActive());
        self::assertFalse($category->showOnFront());
        self::assertCount(0, $category->popEvents());
    }

    // -----------------------------------------------------------------------
    // activate() / deactivate() timestamp updates
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesTimestampOnActivate(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Cat'),
            description: null,
            parentId: null,
        );
        $category->deactivate();
        $before = $category->updatedAt();

        $category->activate();

        self::assertGreaterThanOrEqual($before, $category->updatedAt());
        self::assertTrue($category->isActive());
    }

    #[Test]
    public function itUpdatesTimestampOnDeactivate(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Cat'),
            description: null,
            parentId: null,
        );
        $before = $category->updatedAt();

        $category->deactivate();

        self::assertGreaterThanOrEqual($before, $category->updatedAt());
        self::assertFalse($category->isActive());
    }

    // -----------------------------------------------------------------------
    // coverImage management
    // -----------------------------------------------------------------------

    #[Test]
    public function itAssignsCoverImageUpdatesTimestamp(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Cat'),
            description: null,
            parentId: null,
        );
        $before = $category->updatedAt();

        $category->assignCoverImage('/media/new.jpg');

        self::assertSame('/media/new.jpg', $category->coverImage());
        self::assertGreaterThanOrEqual($before, $category->updatedAt());
    }

    #[Test]
    public function itRemovesCoverImageUpdatesTimestamp(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Cat'),
            description: null,
            parentId: null,
            coverImage: '/media/orig.jpg',
        );
        $before = $category->updatedAt();

        $category->removeCoverImage();

        self::assertNull($category->coverImage());
        self::assertGreaterThanOrEqual($before, $category->updatedAt());
    }

    // -----------------------------------------------------------------------
    // Slug generation
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesSlugWithNumbersPreserved(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Top 10 Products'),
            description: null,
            parentId: null,
        );

        self::assertSame('top-10-products', $category->slug()->value());
    }

    #[Test]
    public function itGeneratesSlugWithMultipleSpaces(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Home   Garden'),
            description: null,
            parentId: null,
        );

        // Multiple spaces become a single hyphen
        self::assertSame('home-garden', $category->slug()->value());
    }

    #[Test]
    public function itGeneratesSlugAllLowercase(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('ELECTRONICS'),
            description: null,
            parentId: null,
        );

        self::assertSame('electronics', $category->slug()->value());
    }

    // -----------------------------------------------------------------------
    // update() detailed field verification
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdateChangesAllMutableFields(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Old Name'),
            description: 'Old description',
            parentId: null,
            position: 0,
            showOnFront: false,
        );

        $newParent = CategoryId::generate();
        $category->update(
            name: CategoryName::fromString('New Name'),
            description: null,
            parentId: $newParent,
            position: 7,
            showOnFront: true,
        );

        self::assertSame('New Name', $category->name()->value());
        self::assertNull($category->description());
        self::assertTrue($category->parentId()->equals($newParent));
        self::assertSame(7, $category->position());
        self::assertTrue($category->showOnFront());
    }

    // -----------------------------------------------------------------------
    // Created with coverImage from factory
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesCategoryWithCoverImageDirectly(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Featured'),
            description: null,
            parentId: null,
            position: 0,
            showOnFront: true,
            coverImage: '/media/featured.jpg',
        );

        self::assertSame('/media/featured.jpg', $category->coverImage());
        self::assertTrue($category->showOnFront());
    }

    // -----------------------------------------------------------------------
    // Initial state
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesNewCategoryAsActiveByDefault(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('New'),
            description: null,
            parentId: null,
        );

        self::assertTrue($category->isActive());
    }

    #[Test]
    public function itCreatesWithTimestampsSetOnCreate(): void
    {
        $before = new \DateTimeImmutable();

        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Timestamped'),
            description: null,
            parentId: null,
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $category->createdAt());
        self::assertLessThanOrEqual($after, $category->createdAt());
        self::assertGreaterThanOrEqual($before, $category->updatedAt());
    }
}
