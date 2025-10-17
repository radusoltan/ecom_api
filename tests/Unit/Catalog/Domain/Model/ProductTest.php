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
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testCreateProductSucceeds(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $sku = SKU::fromString('ELC-TEN-010001');
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
        $this->assertTrue($product->isAvailable());
        $this->assertTrue($product->id()->equals($productId));
        $this->assertTrue($product->tenantId()->equals($tenantId));
        $this->assertSame('ELC-TEN-010001', $product->sku()->value());
        $this->assertSame($description, $product->description());
        $this->assertSame($shortDescription, $product->shortDescription());
        $this->assertSame(199999, $product->price()->getAmount());
        $this->assertSame('USD', $product->price()->getCurrency()->getCurrencyCode());
        $this->assertNull($product->categoryId());
        $this->assertTrue($product->isActive());
        $this->assertFalse($product->isFeatured());
    }

    public function testProductSlugGeneratedFromName(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TEN-010002'),
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
            sku: SKU::fromString('ELC-TEN-010003'),
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
            sku: SKU::fromString('ELC-TEN-010004'),
            name: ProductName::fromString('Backorder Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(0, true, true)
        );

        $this->assertTrue($product->isAvailable());
    }

    public function testProductAvailableWhenInventoryNotTracked(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TEN-010005'),
            name: ProductName::fromString('No Tracking Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(0, false, false)
        );

        $this->assertTrue($product->isAvailable());
    }

    public function testUpdateProductChangesUpdatedAt(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TEN-010006'),
            name: ProductName::fromString('Original Name'),
            description: 'Original description',
            shortDescription: 'Original short',
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10),
            isFeatured: false
        );

        $originalUpdatedAt = $product->updatedAt();

        // Sleep to ensure timestamp difference
        sleep(1);

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
        $this->assertGreaterThan($originalUpdatedAt, $product->updatedAt());
    }

    public function testAddImageToProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TEN-010007'),
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
            sku: SKU::fromString('ELC-TEN-010008'),
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

    public function testDeactivateProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TEN-010009'),
            name: ProductName::fromString('Product to Deactivate'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->assertTrue($product->isActive());

        $product->deactivate();

        $this->assertFalse($product->isActive());
    }

    public function testActivateProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TEN-010010'),
            name: ProductName::fromString('Product to Activate'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product->deactivate();
        $this->assertFalse($product->isActive());

        $product->activate();
        $this->assertTrue($product->isActive());
    }

    public function testUpdateStockQuantity(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TEN-010011'),
            name: ProductName::fromString('Product With Stock'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10, true, false)
        );

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
            sku: SKU::fromString('ELC-TEN-010013'),
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
            sku: SKU::fromString('ELC-TEN-010012'),
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
}
