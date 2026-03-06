<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryTranslationsUpdated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\CategoryWithTranslations;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryWithTranslations::class)]
final class CategoryWithTranslationsTest extends TestCase
{
    private CategoryId $categoryId;
    private TenantId $tenantId;
    private CategoryName $name;

    protected function setUp(): void
    {
        $this->categoryId = CategoryId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->name = CategoryName::fromString('Electronics');
    }

    // -------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesMinimalCategory(): void
    {
        $category = CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: $this->name,
            description: null,
            parentId: null,
        );

        self::assertTrue($category->id()->equals($this->categoryId));
        self::assertTrue($category->tenantId()->equals($this->tenantId));
        self::assertSame('Electronics', $category->name()->value());
        self::assertNull($category->description());
        self::assertSame('electronics', $category->slug()->value());
        self::assertNull($category->parentId());
        self::assertSame(0, $category->position());
        self::assertTrue($category->isActive());
        self::assertFalse($category->showOnFront());
    }

    #[Test]
    public function itCreatesWithAllOptions(): void
    {
        $parentId = CategoryId::generate();

        $category = CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: $this->name,
            description: 'Consumer electronics',
            parentId: $parentId,
            position: 3,
            showOnFront: true,
        );

        self::assertSame('Consumer electronics', $category->description());
        self::assertNotNull($category->parentId());
        self::assertTrue($category->parentId()->equals($parentId));
        self::assertSame(3, $category->position());
        self::assertTrue($category->showOnFront());
    }

    #[Test]
    public function itCreatesWithInitialLocale(): void
    {
        $locale = Locale::fromString('en');

        $category = CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: $this->name,
            description: 'Consumer electronics',
            parentId: null,
            initialLocale: $locale,
        );

        $translations = $category->getTranslationsArray();
        self::assertSame(['en' => 'Electronics'], $translations['name']);
        self::assertSame(['en' => 'Consumer electronics'], $translations['description']);
    }

    #[Test]
    public function itCreatesWithInitialLocaleNullDescription(): void
    {
        $locale = Locale::fromString('ro');

        $category = CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: $this->name,
            description: null,
            parentId: null,
            initialLocale: $locale,
        );

        $translations = $category->getTranslationsArray();
        self::assertSame(['ro' => 'Electronics'], $translations['name']);
        self::assertEmpty($translations['description']);
    }

    #[Test]
    public function itRecordsCategoryCreatedEventOnCreate(): void
    {
        $category = CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: $this->name,
            description: null,
            parentId: null,
        );

        $events = $category->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CategoryCreated::class, $events[0]);
    }

    #[Test]
    public function itGeneratesSlugFromName(): void
    {
        $category = CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: CategoryName::fromString('Home & Garden'),
            description: null,
            parentId: null,
        );

        self::assertSame('home-garden', $category->slug()->value());
    }

    // -------------------------------------------------------------------
    // reconstituteFromPersistence()
    // -------------------------------------------------------------------

    #[Test]
    public function itReconstituteFromPersistenceWithTranslations(): void
    {
        $now = new \DateTimeImmutable('2025-06-01');
        $nameTranslations = LocalizedString::fromArray(['en' => 'Electronics', 'fr' => 'Électronique']);
        $descTranslations = LocalizedString::fromArray(['en' => 'Electronic goods']);

        $category = CategoryWithTranslations::reconstituteFromPersistence(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            defaultName: $this->name,
            defaultDescription: 'Default',
            slug: Slug::fromString('electronics'),
            parentId: null,
            position: 0,
            active: true,
            showOnFront: false,
            createdAt: $now,
            updatedAt: $now,
            nameTranslations: $nameTranslations,
            descriptionTranslations: $descTranslations,
        );

        self::assertTrue($category->getNameTranslations()->equals($nameTranslations));
        self::assertTrue($category->getDescriptionTranslations()->equals($descTranslations));
        self::assertSame($now, $category->createdAt());
        self::assertSame($now, $category->updatedAt());
    }

    // -------------------------------------------------------------------
    // updateTranslations()
    // -------------------------------------------------------------------

    #[Test]
    public function itUpdatesNameTranslation(): void
    {
        $category = $this->createCategory();
        $locale = Locale::fromString('fr');
        $category->popEvents();

        $category->updateTranslations($locale, 'Électronique', null);

        self::assertSame('Électronique', $category->getNameTranslations()->getTranslation($locale));
        $events = $category->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CategoryTranslationsUpdated::class, $events[0]);
    }

    #[Test]
    public function itUpdatesDescriptionTranslation(): void
    {
        $category = $this->createCategory();
        $locale = Locale::fromString('de');

        $category->updateTranslations($locale, null, 'Elektronik-Geräte');

        self::assertSame('Elektronik-Geräte', $category->getDescriptionTranslations()->getTranslation($locale));
    }

    #[Test]
    public function itUpdatesBothTranslationsAtOnce(): void
    {
        $category = $this->createCategory();
        $locale = Locale::fromString('ro');

        $category->updateTranslations($locale, 'Electronice', 'Produse electronice');

        self::assertSame('Electronice', $category->getNameTranslations()->getTranslation($locale));
        self::assertSame('Produse electronice', $category->getDescriptionTranslations()->getTranslation($locale));
    }

    #[Test]
    public function itDoesNotRecordEventWhenNoTranslationFieldsProvided(): void
    {
        $category = $this->createCategory();
        $category->popEvents();

        $category->updateTranslations(Locale::fromString('fr'), null, null);

        self::assertEmpty($category->popEvents());
    }

    #[Test]
    public function itUpdatesUpdatedAtOnTranslationChange(): void
    {
        $category = $this->createCategory();
        $before = $category->updatedAt();

        usleep(1000);
        $category->updateTranslations(Locale::fromString('fr'), 'Updated', null);

        self::assertGreaterThanOrEqual($before, $category->updatedAt());
    }

    // -------------------------------------------------------------------
    // removeTranslations()
    // -------------------------------------------------------------------

    #[Test]
    public function itRemovesTranslationsForLocale(): void
    {
        $category = $this->createCategory();
        $locale = Locale::fromString('fr');

        $category->updateTranslations($locale, 'Électronique', 'Description française');
        self::assertNotNull($category->getNameTranslations()->getTranslation($locale));

        $category->popEvents();
        $category->removeTranslations($locale);

        self::assertNull($category->getNameTranslations()->getTranslation($locale));
        self::assertNull($category->getDescriptionTranslations()->getTranslation($locale));

        $events = $category->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CategoryTranslationsUpdated::class, $events[0]);
    }

    // -------------------------------------------------------------------
    // getName() / getDescription() via LocalizationPolicy
    // -------------------------------------------------------------------

    #[Test]
    public function itGetsNameForLocaleWithTranslation(): void
    {
        $category = $this->createCategory();
        $locale = Locale::fromString('fr');
        $category->updateTranslations($locale, 'Électronique', null);

        $policy = new LocalizationPolicy();
        $name = $category->getName($locale, $policy);

        self::assertSame('Électronique', $name);
    }

    #[Test]
    public function itFallsBackToDefaultNameWhenNoTranslation(): void
    {
        $category = $this->createCategory();
        $policy = new LocalizationPolicy();

        $name = $category->getName(Locale::fromString('ja'), $policy);

        self::assertSame('Electronics', $name);
    }

    #[Test]
    public function itGetsDescriptionWithFallback(): void
    {
        $category = CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: $this->name,
            description: 'Default description',
            parentId: null,
        );

        $policy = new LocalizationPolicy();
        $desc = $category->getDescription(Locale::fromString('ja'), $policy);

        self::assertSame('Default description', $desc);
    }

    #[Test]
    public function itGetsNullDescriptionWhenNoneSet(): void
    {
        $category = $this->createCategory();
        $policy = new LocalizationPolicy();

        self::assertNull($category->getDescription(Locale::fromString('en'), $policy));
    }

    // -------------------------------------------------------------------
    // getSlug()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsDefaultSlugWhenNoLocalizedName(): void
    {
        $category = $this->createCategory();
        $policy = new LocalizationPolicy();

        $slug = $category->getSlug(Locale::fromString('fr'), $policy);

        self::assertSame('electronics', $slug->value());
    }

    #[Test]
    public function itGeneratesLocalizedSlugFromLocalizedName(): void
    {
        $category = $this->createCategory();
        $locale = Locale::fromString('fr');
        $category->updateTranslations($locale, 'Électronique Grand Public', null);

        $policy = new LocalizationPolicy();
        $slug = $category->getSlug($locale, $policy);

        self::assertSame('lectronique-grand-public', $slug->value());
    }

    // -------------------------------------------------------------------
    // getTranslationsArray()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyTranslationsArrayWhenNoneSet(): void
    {
        $category = $this->createCategory();

        $array = $category->getTranslationsArray();

        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('description', $array);
        self::assertEmpty($array['name']);
        self::assertEmpty($array['description']);
    }

    #[Test]
    public function itReturnsPopulatedTranslationsArray(): void
    {
        $category = $this->createCategory();
        $category->updateTranslations(Locale::fromString('en'), 'English Name', 'English Desc');
        $category->updateTranslations(Locale::fromString('fr'), 'Nom Français', null);

        $array = $category->getTranslationsArray();

        self::assertSame(['en' => 'English Name', 'fr' => 'Nom Français'], $array['name']);
        self::assertSame(['en' => 'English Desc'], $array['description']);
    }

    // -------------------------------------------------------------------
    // update() / activate() / deactivate()
    // -------------------------------------------------------------------

    #[Test]
    public function itUpdatesDefaultFields(): void
    {
        $category = $this->createCategory();
        $category->popEvents();

        $newParentId = CategoryId::generate();
        $category->update(
            CategoryName::fromString('Updated Electronics'),
            'Updated description',
            $newParentId,
            5,
            true
        );

        self::assertSame('Updated Electronics', $category->name()->value());
        self::assertSame('Updated description', $category->description());
        self::assertSame(5, $category->position());
        self::assertTrue($category->showOnFront());

        $events = $category->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CategoryUpdated::class, $events[0]);
    }

    #[Test]
    public function itActivatesAndDeactivatesCategory(): void
    {
        $category = $this->createCategory();
        self::assertTrue($category->isActive());

        $category->deactivate();
        self::assertFalse($category->isActive());

        $category->activate();
        self::assertTrue($category->isActive());
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function createCategory(): CategoryWithTranslations
    {
        return CategoryWithTranslations::create(
            id: $this->categoryId,
            tenantId: $this->tenantId,
            name: $this->name,
            description: null,
            parentId: null,
        );
    }
}
