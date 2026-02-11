<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ProductEntityTest extends TestCase
{
    private ProductId $productId;
    private TenantId $tenantId;
    private CategoryId $categoryId;

    protected function setUp(): void
    {
        $this->productId = ProductId::generate();
        $this->tenantId = TenantId::generate();
        $this->categoryId = CategoryId::generate();
    }

    public function testFromDomainModelConvertsProductCorrectly(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000001'),
            name: ProductName::fromString('Test Product'),
            description: 'Product description',
            shortDescription: 'Short desc',
            price: Money::of('99.99', 'USD'),
            categoryId: $this->categoryId,
            stock: Stock::create(100, true, false),
            isFeatured: true
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertSame($this->productId->toString(), $entity->getId());
        $this->assertSame($this->tenantId->toString(), $entity->getTenantId());
        $this->assertSame('PRD-000001', $entity->getSku());
        $this->assertSame('Test Product', $entity->getName());
        $this->assertSame('Product description', $entity->getDescription());
        $this->assertSame('Short desc', $entity->getShortDescription());
        $this->assertSame(9999, $entity->getPriceAmount()); // Money stores in cents
        $this->assertSame('USD', $entity->getPriceCurrency());
        $this->assertSame($this->categoryId->toString(), $entity->getCategoryId());
        $this->assertSame(100, $entity->getStockQuantity());
        $this->assertTrue($entity->isTrackInventory());
        $this->assertFalse($entity->isAllowBackorder());
        // New products start as DRAFT (not ACTIVE) per business rules
        $this->assertFalse($entity->isActive());
        $this->assertTrue($entity->isFeatured());
    }

    public function testToDomainModelReconstitutesProductCorrectly(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000002'),
            name: ProductName::fromString('Entity Product'),
            description: 'Entity description',
            shortDescription: 'Entity short',
            price: Money::of('49.99', 'EUR'),
            categoryId: $this->categoryId,
            stock: Stock::create(50, false, true),
            isFeatured: false
        );

        // First activate (draft -> active), then deactivate (active -> inactive)
        $product->activate();
        $product->deactivate();

        $entity = ProductEntity::fromDomainModel($product);
        $reconstituted = $entity->toDomainModel();

        $this->assertTrue($reconstituted->id()->equals($this->productId));
        $this->assertTrue($reconstituted->tenantId()->equals($this->tenantId));
        $this->assertSame('PRD-000002', $reconstituted->sku()->value());
        $this->assertSame('Entity Product', $reconstituted->name()->value());
        $this->assertSame('Entity description', $reconstituted->description());
        $this->assertSame('Entity short', $reconstituted->shortDescription());
        $this->assertSame(4999, $reconstituted->price()->getAmount());
        $this->assertSame('EUR', $reconstituted->price()->getCurrency()->getCurrencyCode());
        $this->assertTrue($reconstituted->categoryId()->equals($this->categoryId));
        $this->assertSame(50, $reconstituted->stock()->quantity());
        $this->assertFalse($reconstituted->stock()->trackInventory());
        $this->assertTrue($reconstituted->stock()->allowBackorder());
        $this->assertFalse($reconstituted->isActive());
        $this->assertFalse($reconstituted->isFeatured());
    }

    public function testSettersWorkCorrectly(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000003'),
            name: ProductName::fromString('Original Name'),
            description: 'Original desc',
            shortDescription: 'Short',
            price: Money::of('10.00', 'USD'),
            categoryId: null,
            stock: Stock::create(10, true, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        // Test setters
        $entity->setTenantId('new-tenant-id');
        $this->assertSame('new-tenant-id', $entity->getTenantId());

        $entity->setSku('ELC-TEN-999999');
        $this->assertSame('ELC-TEN-999999', $entity->getSku());

        $entity->setName('New Name');
        $this->assertSame('New Name', $entity->getName());

        $entity->setDescription('New description');
        $this->assertSame('New description', $entity->getDescription());

        $entity->setShortDescription('New short');
        $this->assertSame('New short', $entity->getShortDescription());

        $entity->setPriceAmount(1999);
        $this->assertSame(1999, $entity->getPriceAmount());

        $entity->setPriceCurrency('GBP');
        $this->assertSame('GBP', $entity->getPriceCurrency());

        $newCategoryId = CategoryId::generate();
        $entity->setCategoryId($newCategoryId->toString());
        $this->assertSame($newCategoryId->toString(), $entity->getCategoryId());

        $entity->setStockQuantity(75);
        $this->assertSame(75, $entity->getStockQuantity());

        $entity->setTrackInventory(false);
        $this->assertFalse($entity->isTrackInventory());

        $entity->setAllowBackorder(true);
        $this->assertTrue($entity->isAllowBackorder());

        $entity->setActive(false);
        $this->assertFalse($entity->isActive());
    }

    public function testImagesHandling(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000010'),
            name: ProductName::fromString('Product with Images'),
            description: 'Description',
            shortDescription: 'Short',
            price: Money::of('29.99', 'USD'),
            categoryId: $this->categoryId,
            stock: Stock::create(10, true, false)
        );

        $product->addImage(ProductImage::create(
            'https://example.com/primary.jpg',
            0,
            true
        ));
        $product->addImage(ProductImage::create(
            'https://example.com/secondary.jpg',
            1,
            false
        ));

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertCount(2, $entity->getImages());
        $this->assertTrue($entity->getImages()[0]['isPrimary']);
        $this->assertFalse($entity->getImages()[1]['isPrimary']);

        // Test setImages
        $newImages = [
            ['url' => 'https://example.com/img1.jpg', 'alt' => 'Image 1', 'isPrimary' => true, 'position' => 0],
        ];
        $entity->setImages($newImages);
        $this->assertCount(1, $entity->getImages());
        $this->assertSame($newImages, $entity->getImages());
    }

    public function testProductWithNullCategoryId(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('GEN-000011'),
            name: ProductName::fromString('Uncategorized Product'),
            description: 'No category',
            shortDescription: 'Short',
            price: Money::of('15.00', 'USD'),
            categoryId: null,
            stock: Stock::create(5, true, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertNull($entity->getCategoryId());

        // Round trip
        $reconstituted = $entity->toDomainModel();
        $this->assertNull($reconstituted->categoryId());
    }

    public function testSlugIsGeneratedAutomatically(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000012'),
            name: ProductName::fromString('Test Product Slug'),
            description: 'Slug test',
            shortDescription: 'Short',
            price: Money::of('10.00', 'USD'),
            categoryId: null,
            stock: Stock::create(1, true, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertSame('test-product-slug', $entity->getSlug());
    }

    public function testProductWithBackorderAllowed(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-123456'),
            name: ProductName::fromString('Backorder Product'),
            description: 'Allows backorder',
            shortDescription: 'Backorder',
            price: Money::of('99.99', 'USD'),
            categoryId: $this->categoryId,
            stock: Stock::create(0, true, true)
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertSame(0, $entity->getStockQuantity());
        $this->assertTrue($entity->isAllowBackorder());
        $this->assertTrue($entity->isTrackInventory());
    }

    public function testProductWithoutInventoryTracking(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-123456'),
            name: ProductName::fromString('Unlimited Product'),
            description: 'No inventory tracking',
            shortDescription: 'Unlimited',
            price: Money::of('5.99', 'USD'),
            categoryId: $this->categoryId,
            stock: Stock::create(0, false, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertSame(0, $entity->getStockQuantity());
        $this->assertFalse($entity->isTrackInventory());
        $this->assertFalse($entity->isAllowBackorder());
    }

    public function testCreatedAtAndUpdatedAtArePreserved(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000013'),
            name: ProductName::fromString('Date Test'),
            description: 'Testing dates',
            shortDescription: 'Dates',
            price: Money::of('1.00', 'USD'),
            categoryId: null,
            stock: Stock::create(1, true, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        $reconstituted = $entity->toDomainModel();

        $this->assertEquals($product->createdAt(), $reconstituted->createdAt());
        $this->assertEquals($product->updatedAt(), $reconstituted->updatedAt());
    }

    public function testEmptyImagesArray(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000014'),
            name: ProductName::fromString('No Images'),
            description: 'No images',
            shortDescription: 'Empty',
            price: Money::of('5.00', 'USD'),
            categoryId: null,
            stock: Stock::create(1, true, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertSame([], $entity->getImages());
    }

    public function testNullableDescriptions(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000015'),
            name: ProductName::fromString('Null Descriptions'),
            description: null,
            shortDescription: null,
            price: Money::of('1.00', 'USD'),
            categoryId: null,
            stock: Stock::create(1, true, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        $this->assertNull($entity->getDescription());
        $this->assertNull($entity->getShortDescription());
    }

    public function testRoundTripConversionPreservesAllData(): void
    {
        $originalProduct = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000016'),
            name: ProductName::fromString('Round Trip Test'),
            description: 'Testing round trip',
            shortDescription: 'Round trip',
            price: Money::of('123.45', 'EUR'),
            categoryId: $this->categoryId,
            stock: Stock::create(99, true, false),
            isFeatured: true
        );

        $originalProduct->addImage(ProductImage::create(
            'https://example.com/test.jpg',
            0,
            true
        ));

        // Convert to entity
        $entity = ProductEntity::fromDomainModel($originalProduct);

        // Convert back to domain
        $reconstituted = $entity->toDomainModel();

        // Verify all properties match
        $this->assertTrue($originalProduct->id()->equals($reconstituted->id()));
        $this->assertTrue($originalProduct->tenantId()->equals($reconstituted->tenantId()));
        $this->assertSame($originalProduct->sku()->value(), $reconstituted->sku()->value());
        $this->assertSame($originalProduct->name()->value(), $reconstituted->name()->value());
        $this->assertSame($originalProduct->description(), $reconstituted->description());
        $this->assertSame($originalProduct->shortDescription(), $reconstituted->shortDescription());
        $this->assertSame($originalProduct->slug()->value(), $reconstituted->slug()->value());
        $this->assertSame($originalProduct->price()->getAmount(), $reconstituted->price()->getAmount());
        $this->assertSame($originalProduct->price()->getCurrency()->getCurrencyCode(), $reconstituted->price()->getCurrency()->getCurrencyCode());
        $this->assertTrue($originalProduct->categoryId()->equals($reconstituted->categoryId()));
        $this->assertSame($originalProduct->stock()->quantity(), $reconstituted->stock()->quantity());
        $this->assertSame($originalProduct->stock()->trackInventory(), $reconstituted->stock()->trackInventory());
        $this->assertSame($originalProduct->stock()->allowBackorder(), $reconstituted->stock()->allowBackorder());
        $this->assertCount(count($originalProduct->images()), $reconstituted->images());
        $this->assertSame($originalProduct->isActive(), $reconstituted->isActive());
        $this->assertSame($originalProduct->isFeatured(), $reconstituted->isFeatured());
    }

    public function testSetTranslatableLocale(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-000017'),
            name: ProductName::fromString('Locale Test'),
            description: 'Testing locale',
            shortDescription: 'Locale',
            price: Money::of('1.00', 'USD'),
            categoryId: null,
            stock: Stock::create(1, true, false)
        );

        $entity = ProductEntity::fromDomainModel($product);

        // This method should not throw an error
        $entity->setTranslatableLocale('en');
        $entity->setTranslatableLocale('fr');
        $entity->setTranslatableLocale(null);

        $this->assertTrue(true); // If we got here, the method works
    }
}
