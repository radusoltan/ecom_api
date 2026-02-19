<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testCreateProductSucceeds(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $sku = SKU::fromString('ELC-010001');
        $name = ProductName::fromString('Dell XPS 15');
        $description = 'High-performance laptop';
        $shortDescription = 'Premium laptop';
        $price = Money::fromScalars(199999, 'USD');
        $stock = Stock::create(10, true, false);

        $product = Product::create(
            id: $productId,
            tenantId: $tenantId,
            sku: $sku,
            name: $name,
            description: $description,
            shortDescription: $shortDescription,
            price: $price,
            categoryId: null,
            stock: $stock
        );

        $this->assertEquals('Dell XPS 15', $product->name()->value());
        $this->assertSame('dell-xps-15', $product->slug()->value());
        $this->assertTrue($product->id()->equals($productId));
        $this->assertTrue($product->tenantId()->equals($tenantId));
        $this->assertSame('ELC-010001', $product->sku()->value());
        $this->assertSame($description, $product->description());
        $this->assertSame($shortDescription, $product->shortDescription());
        $this->assertSame(199999, $product->price()->getAmount());
        $this->assertSame('USD', $product->price()->getCurrency()->getCurrencyCode());
        $this->assertNull($product->categoryId());
        $this->assertFalse($product->isFeatured());

        // New products start as DRAFT, not ACTIVE
        $this->assertTrue($product->status()->isDraft());
        $this->assertFalse($product->isActive());
        $this->assertFalse($product->isAvailable());

        // After publishing, product becomes available
        $product->publish();
        $this->assertTrue($product->isActive());
        $this->assertTrue($product->isAvailable());
    }

    public function testProductSlugGeneratedFromName(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010002'),
            name: ProductName::fromString('Test Product Name With Spaces'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertSame('test-product-name-with-spaces', $product->slug()->value());
    }

    public function testProductNotAvailableWhenOutOfStock(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010003'),
            name: ProductName::fromString('Out of Stock Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(0, true, false)
        );

        $this->assertFalse($product->isAvailable());
    }

    public function testProductAvailableWhenBackorderAllowed(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010004'),
            name: ProductName::fromString('Backorder Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(0, true, true)
        );

        // Must publish first
        $product->publish();

        $this->assertTrue($product->isAvailable());
    }

    public function testProductAvailableWhenInventoryNotTracked(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010005'),
            name: ProductName::fromString('No Tracking Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(0, false, false)
        );

        // Must publish first
        $product->publish();

        $this->assertTrue($product->isAvailable());
    }

    public function testUpdateProductChangesUpdatedAt(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010006'),
            name: ProductName::fromString('Original Name'),
            description: 'Original description',
            shortDescription: 'Original short',
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10),
            isFeatured: false
        );

        $originalUpdatedAt = $product->updatedAt();

        $product->update(
            name: ProductName::fromString('Updated Name'),
            description: 'Updated description',
            shortDescription: 'Updated short',
            price: Money::fromScalars(6000, 'USD'),
            categoryId: null,
            isFeatured: true
        );

        $this->assertEquals('Updated Name', $product->name()->value());
        $this->assertSame('Updated description', $product->description());
        $this->assertSame('Updated short', $product->shortDescription());
        $this->assertSame(6000, $product->price()->getAmount());
        $this->assertTrue($product->isFeatured());
        $this->assertNotEquals($originalUpdatedAt, $product->updatedAt());
    }

    public function testAddImageToProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010007'),
            name: ProductName::fromString('Product With Images'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertCount(0, $product->images());

        $image = ProductImage::create('https://example.com/image1.jpg', 0, true);
        $product->addImage($image);

        $this->assertCount(1, $product->images());
        $this->assertSame('https://example.com/image1.jpg', $product->images()[0]->url());
        $this->assertTrue($product->images()[0]->isPrimary());
    }

    public function testRemoveImageFromProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010008'),
            name: ProductName::fromString('Product With Images'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $image1 = ProductImage::create('https://example.com/image1.jpg', 0, true);
        $image2 = ProductImage::create('https://example.com/image2.jpg', 1, false);

        $product->addImage($image1);
        $product->addImage($image2);

        $this->assertCount(2, $product->images());

        $product->removeImage(0);

        $remainingImages = array_values($product->images());
        $this->assertCount(1, $remainingImages);
        $this->assertSame('https://example.com/image2.jpg', $remainingImages[0]->url());
    }

    // ========== Status Transition Tests ==========

    public function testNewProductStartsAsDraft(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010009'),
            name: ProductName::fromString('New Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertTrue($product->status()->isDraft());
        $this->assertFalse($product->isActive());
    }

    public function testPublishProductFromDraftToActive(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010010'),
            name: ProductName::fromString('Product to Publish'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertTrue($product->status()->isDraft());

        $product->publish();

        $this->assertTrue($product->status()->isActive());
        $this->assertTrue($product->isActive());
    }

    public function testCannotPublishNonDraftProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010011'),
            name: ProductName::fromString('Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product->publish(); // Now ACTIVE

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot publish product in status "active"');

        $product->publish(); // Should fail
    }

    public function testDeactivateActiveProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010012'),
            name: ProductName::fromString('Product to Deactivate'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product->publish(); // Make it ACTIVE first
        $this->assertTrue($product->status()->isActive());

        $product->deactivate();

        $this->assertTrue($product->status()->isInactive());
        $this->assertFalse($product->isActive());
    }

    public function testCannotDeactivateNonActiveProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010013'),
            name: ProductName::fromString('Draft Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot deactivate product in status "draft"');

        $product->deactivate(); // Should fail - product is DRAFT
    }

    public function testReactivateInactiveProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010014'),
            name: ProductName::fromString('Product to Reactivate'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product->publish(); // DRAFT → ACTIVE
        $product->deactivate(); // ACTIVE → INACTIVE
        $this->assertTrue($product->status()->isInactive());

        $product->reactivate(); // INACTIVE → ACTIVE

        $this->assertTrue($product->status()->isActive());
        $this->assertTrue($product->isActive());
    }

    public function testCannotReactivateNonInactiveProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010015'),
            name: ProductName::fromString('Draft Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reactivate product in status "draft"');

        $product->reactivate(); // Should fail - product is DRAFT
    }

    public function testDiscontinueProductFromAnyStatus(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010016'),
            name: ProductName::fromString('Product to Discontinue'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertTrue($product->status()->isDraft());

        $product->discontinue();

        $this->assertTrue($product->status()->isDiscontinued());
        $this->assertFalse($product->isActive());
    }

    public function testDiscontinuedProductCannotTransitionToAnyStatus(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010017'),
            name: ProductName::fromString('Discontinued Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product->discontinue();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product is already discontinued');

        $product->discontinue(); // Should fail - already discontinued
    }

    public function testBackwardCompatibilityActivateFromDraft(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010018'),
            name: ProductName::fromString('Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertTrue($product->status()->isDraft());

        $product->activate(); // Should call publish()

        $this->assertTrue($product->status()->isActive());
    }

    public function testBackwardCompatibilityActivateFromInactive(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010019'),
            name: ProductName::fromString('Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product->publish(); // DRAFT → ACTIVE
        $product->deactivate(); // ACTIVE → INACTIVE
        $this->assertTrue($product->status()->isInactive());

        $product->activate(); // Should call reactivate()

        $this->assertTrue($product->status()->isActive());
    }

    public function testUpdateStockQuantity(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010011'),
            name: ProductName::fromString('Product With Stock'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10, true, false)
        );

        // Must publish first
        $product->publish();

        $this->assertTrue($product->isAvailable());

        $newStock = $product->stock()->decrease(5);
        $product->updateStock($newStock);

        $this->assertTrue($product->isAvailable());
        $this->assertSame(5, $product->stock()->quantity());
    }

    public function testCreateFeaturedProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010020'),
            name: ProductName::fromString('Featured Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(10000, 'USD'),
            categoryId: null,
            stock: Stock::create(5),
            isFeatured: true
        );

        $this->assertTrue($product->isFeatured());
    }

    public function testProductWithCategory(): void
    {
        $categoryId = CategoryId::generate();

        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010021'),
            name: ProductName::fromString('Product With Category'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: $categoryId,
            stock: Stock::create(10)
        );

        $this->assertNotNull($product->categoryId());
        $this->assertTrue($product->categoryId()->equals($categoryId));
    }

    // ========== Product Type Tests ==========

    public function testNewProductDefaultsToSimpleType(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010022'),
            name: ProductName::fromString('Simple Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertTrue($product->type()->isSimple());
    }

    public function testCreateProductWithSpecificType(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010023'),
            name: ProductName::fromString('Configurable Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10),
            type: ProductType::configurable()
        );

        $this->assertTrue($product->type()->isConfigurable());
    }

    public function testChangeProductType(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010024'),
            name: ProductName::fromString('Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertTrue($product->type()->isSimple());

        $product->changeType(ProductType::configurable());

        $this->assertTrue($product->type()->isConfigurable());
        $this->assertFalse($product->type()->isSimple());
    }

    public function testCannotChangeToSameType(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010025'),
            name: ProductName::fromString('Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product is already of type "simple"');

        $product->changeType(ProductType::simple());
    }

    public function testCreateVirtualProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010026'),
            name: ProductName::fromString('Digital Download'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(2999, 'USD'),
            categoryId: null,
            stock: Stock::create(9999, false, false), // Virtual products don't track inventory
            type: ProductType::virtual()
        );

        $this->assertTrue($product->type()->isVirtual());
        $this->assertFalse($product->type()->requiresShipping());
    }

    public function testCreateBundleProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010027'),
            name: ProductName::fromString('Gift Bundle'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(19999, 'USD'),
            categoryId: null,
            stock: Stock::create(5),
            type: ProductType::bundle()
        );

        $this->assertTrue($product->type()->isBundle());
        $this->assertTrue($product->type()->isComposite());
    }

    public function testCreateSubscriptionProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010028'),
            name: ProductName::fromString('Monthly Subscription'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(999, 'USD'),
            categoryId: null,
            stock: Stock::create(9999, false, false),
            type: ProductType::subscription()
        );

        $this->assertTrue($product->type()->isSubscription());
        $this->assertFalse($product->type()->requiresShipping());
    }

    public function testChangeProductTypeUpdatesTimestamp(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-010029'),
            name: ProductName::fromString('Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $originalUpdatedAt = $product->updatedAt();

        $product->changeType(ProductType::bundle());

        $this->assertNotEquals($originalUpdatedAt, $product->updatedAt());
        $this->assertTrue($product->type()->isBundle());
    }
}
