<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductTranslationsUpdated;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\ProductWithTranslations;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductWithTranslations::class)]
final class ProductWithTranslationsTest extends TestCase
{
    private ProductId $productId;
    private TenantId $tenantId;
    private SKU $sku;
    private ProductName $name;
    private Money $price;
    private Stock $stock;

    protected function setUp(): void
    {
        $this->productId = ProductId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->sku = SKU::fromString('TST-000001');
        $this->name = ProductName::fromString('Test Product');
        $this->price = Money::of('10.00', 'USD');
        $this->stock = Stock::create(100, true, false);
    }

    // -------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesProductWithoutInitialLocale(): void
    {
        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: 'A description',
            shortDescription: 'Short',
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
        );

        self::assertTrue($product->id()->equals($this->productId));
        self::assertTrue($product->tenantId()->equals($this->tenantId));
        self::assertSame('TST-000001', $product->sku()->value());
        self::assertSame('Test Product', $product->name()->value());
        self::assertSame('A description', $product->description());
        self::assertSame('Short', $product->shortDescription());
        self::assertSame('test-product', $product->slug()->value());
        self::assertTrue($product->isActive());
        self::assertFalse($product->isFeatured());
        self::assertNull($product->categoryId());
        self::assertEmpty($product->images());
    }

    #[Test]
    public function itCreatesProductWithInitialLocale(): void
    {
        $locale = Locale::fromString('en');

        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: 'A description',
            shortDescription: 'Short',
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
            initialLocale: $locale,
        );

        $translations = $product->getTranslationsArray();
        self::assertSame(['en' => 'Test Product'], $translations['name']);
        self::assertSame(['en' => 'A description'], $translations['description']);
        self::assertSame(['en' => 'Short'], $translations['short_description']);
    }

    #[Test]
    public function itCreatesProductWithInitialLocaleAndNullDescriptions(): void
    {
        $locale = Locale::fromString('en');

        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: null,
            shortDescription: null,
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
            initialLocale: $locale,
        );

        $translations = $product->getTranslationsArray();
        self::assertSame(['en' => 'Test Product'], $translations['name']);
        self::assertEmpty($translations['description']);
        self::assertEmpty($translations['short_description']);
    }

    #[Test]
    public function itRecordsProductCreatedEventOnCreate(): void
    {
        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: null,
            shortDescription: null,
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
        );

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductCreated::class, $events[0]);
    }

    #[Test]
    public function itCreatesFeaturedProduct(): void
    {
        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: null,
            shortDescription: null,
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
            isFeatured: true,
        );

        self::assertTrue($product->isFeatured());
    }

    #[Test]
    public function itCreatesProductWithCategoryId(): void
    {
        $categoryId = CategoryId::generate();

        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: null,
            shortDescription: null,
            price: $this->price,
            categoryId: $categoryId,
            stock: $this->stock,
        );

        self::assertNotNull($product->categoryId());
        self::assertTrue($product->categoryId()->equals($categoryId));
    }

    // -------------------------------------------------------------------
    // reconstituteFromPersistence()
    // -------------------------------------------------------------------

    #[Test]
    public function itReconstituteFromPersistenceWithTranslations(): void
    {
        $now = new \DateTimeImmutable('2025-01-01');
        $nameTranslations = LocalizedString::fromArray(['en' => 'English Name', 'fr' => 'Nom Français']);
        $descTranslations = LocalizedString::fromArray(['en' => 'Description']);
        $shortDescTranslations = LocalizedString::empty();

        $product = ProductWithTranslations::reconstituteFromPersistence(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            defaultName: $this->name,
            defaultDescription: 'Default desc',
            defaultShortDescription: null,
            slug: Slug::fromString('test-product'),
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
            images: [],
            active: true,
            isFeatured: false,
            createdAt: $now,
            updatedAt: $now,
            nameTranslations: $nameTranslations,
            descriptionTranslations: $descTranslations,
            shortDescriptionTranslations: $shortDescTranslations,
        );

        self::assertTrue($product->getNameTranslations()->equals($nameTranslations));
        self::assertTrue($product->getDescriptionTranslations()->equals($descTranslations));
        self::assertTrue($product->getShortDescriptionTranslations()->equals($shortDescTranslations));
        self::assertSame($now, $product->createdAt());
    }

    // -------------------------------------------------------------------
    // updateTranslations()
    // -------------------------------------------------------------------

    #[Test]
    public function itUpdatesNameTranslation(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('fr');

        $product->popEvents(); // clear creation event

        $product->updateTranslations($locale, 'Produit Test', null, null);

        self::assertSame('Produit Test', $product->getNameTranslations()->getTranslation($locale));
        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductTranslationsUpdated::class, $events[0]);
    }

    #[Test]
    public function itUpdatesDescriptionTranslation(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('de');

        $product->updateTranslations($locale, null, 'Beschreibung', null);

        self::assertSame('Beschreibung', $product->getDescriptionTranslations()->getTranslation($locale));
    }

    #[Test]
    public function itUpdatesShortDescriptionTranslation(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('es');

        $product->updateTranslations($locale, null, null, 'Descripción corta');

        self::assertSame('Descripción corta', $product->getShortDescriptionTranslations()->getTranslation($locale));
    }

    #[Test]
    public function itUpdatesAllTranslationsAtOnce(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('ro');

        $product->updateTranslations($locale, 'Produs Test', 'Descriere', 'Descriere scurta');

        self::assertSame('Produs Test', $product->getNameTranslations()->getTranslation($locale));
        self::assertSame('Descriere', $product->getDescriptionTranslations()->getTranslation($locale));
        self::assertSame('Descriere scurta', $product->getShortDescriptionTranslations()->getTranslation($locale));
    }

    #[Test]
    public function itDoesNotRecordEventWhenNoTranslationFieldsProvided(): void
    {
        $product = $this->createProduct();
        $product->popEvents();

        $product->updateTranslations(Locale::fromString('fr'), null, null, null);

        self::assertEmpty($product->popEvents());
    }

    #[Test]
    public function itUpdatesUpdatedAtOnTranslationChange(): void
    {
        $product = $this->createProduct();
        $beforeUpdate = $product->updatedAt();

        // Small sleep to ensure timestamp difference
        usleep(1000);

        $product->updateTranslations(Locale::fromString('fr'), 'Produit', null, null);

        self::assertGreaterThanOrEqual($beforeUpdate, $product->updatedAt());
    }

    // -------------------------------------------------------------------
    // removeTranslations()
    // -------------------------------------------------------------------

    #[Test]
    public function itRemovesTranslationsForLocale(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('fr');

        $product->updateTranslations($locale, 'Produit Test', 'Description', 'Court');
        self::assertNotNull($product->getNameTranslations()->getTranslation($locale));

        $product->popEvents();
        $product->removeTranslations($locale);

        self::assertNull($product->getNameTranslations()->getTranslation($locale));
        self::assertNull($product->getDescriptionTranslations()->getTranslation($locale));
        self::assertNull($product->getShortDescriptionTranslations()->getTranslation($locale));

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductTranslationsUpdated::class, $events[0]);
    }

    // -------------------------------------------------------------------
    // getName() / getDescription() / getShortDescription() via LocalizationPolicy
    // -------------------------------------------------------------------

    #[Test]
    public function itGetsNameForLocaleWithTranslation(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('fr');
        $product->updateTranslations($locale, 'Produit Test', null, null);

        $policy = new LocalizationPolicy();
        $name = $product->getName($locale, $policy);

        self::assertSame('Produit Test', $name);
    }

    #[Test]
    public function itFallsBackToDefaultNameWhenNoTranslation(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('ja');
        $policy = new LocalizationPolicy();

        $name = $product->getName($locale, $policy);

        self::assertSame('Test Product', $name);
    }

    #[Test]
    public function itGetsDescriptionWithFallback(): void
    {
        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: 'Default description',
            shortDescription: null,
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
        );

        $policy = new LocalizationPolicy();
        $desc = $product->getDescription(Locale::fromString('ja'), $policy);

        self::assertSame('Default description', $desc);
    }

    #[Test]
    public function itGetsNullDescriptionWhenNoneSet(): void
    {
        $product = ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: null,
            shortDescription: null,
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
        );

        $policy = new LocalizationPolicy();
        self::assertNull($product->getDescription(Locale::fromString('en'), $policy));
        self::assertNull($product->getShortDescription(Locale::fromString('en'), $policy));
    }

    // -------------------------------------------------------------------
    // getSlug()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsDefaultSlugWhenNoLocalizedName(): void
    {
        $product = $this->createProduct();
        $policy = new LocalizationPolicy();

        $slug = $product->getSlug(Locale::fromString('fr'), $policy);

        self::assertSame('test-product', $slug->value());
    }

    #[Test]
    public function itGeneratesLocalizedSlugFromLocalizedName(): void
    {
        $product = $this->createProduct();
        $locale = Locale::fromString('fr');
        $product->updateTranslations($locale, 'Produit de Test', null, null);

        $policy = new LocalizationPolicy();
        $slug = $product->getSlug($locale, $policy);

        self::assertSame('produit-de-test', $slug->value());
    }

    // -------------------------------------------------------------------
    // getTranslationsArray()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyTranslationsArrayWhenNoneSet(): void
    {
        $product = $this->createProduct();

        $array = $product->getTranslationsArray();

        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('description', $array);
        self::assertArrayHasKey('short_description', $array);
        self::assertEmpty($array['name']);
        self::assertEmpty($array['description']);
        self::assertEmpty($array['short_description']);
    }

    #[Test]
    public function itReturnsPopulatedTranslationsArray(): void
    {
        $product = $this->createProduct();
        $product->updateTranslations(Locale::fromString('en'), 'English Name', 'English Desc', 'Short');
        $product->updateTranslations(Locale::fromString('fr'), 'Nom Français', null, null);

        $array = $product->getTranslationsArray();

        self::assertSame(['en' => 'English Name', 'fr' => 'Nom Français'], $array['name']);
        self::assertSame(['en' => 'English Desc'], $array['description']);
        self::assertSame(['en' => 'Short'], $array['short_description']);
    }

    // -------------------------------------------------------------------
    // update() / updateStock() / addImage() / removeImage()
    // -------------------------------------------------------------------

    #[Test]
    public function itUpdatesDefaultFields(): void
    {
        $product = $this->createProduct();
        $product->popEvents();

        $newName = ProductName::fromString('Updated Product');
        $newPrice = Money::of('20.00', 'USD');

        $product->update($newName, 'New description', 'New short', $newPrice, null, true);

        self::assertSame('Updated Product', $product->name()->value());
        self::assertSame('New description', $product->description());
        self::assertSame('New short', $product->shortDescription());
        self::assertTrue($product->isFeatured());

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductUpdated::class, $events[0]);
    }

    #[Test]
    public function itUpdatesStock(): void
    {
        $product = $this->createProduct();
        $newStock = Stock::create(50, true, false);

        $product->updateStock($newStock);

        self::assertSame(50, $product->stock()->quantity());
    }

    #[Test]
    public function itActivatesAndDeactivatesProduct(): void
    {
        $product = $this->createProduct();
        self::assertTrue($product->isActive());

        $product->deactivate();
        self::assertFalse($product->isActive());

        $product->activate();
        self::assertTrue($product->isActive());
    }

    #[Test]
    public function itChecksAvailability(): void
    {
        $product = $this->createProduct();
        self::assertTrue($product->isAvailable());
        self::assertTrue($product->isAvailable(50));

        $product->deactivate();
        self::assertFalse($product->isAvailable());
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function createProduct(): ProductWithTranslations
    {
        return ProductWithTranslations::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: 'A description',
            shortDescription: 'Short desc',
            price: $this->price,
            categoryId: null,
            stock: $this->stock,
        );
    }
}
