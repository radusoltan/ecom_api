<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Event\DownloadableFileAttached;
use App\Catalog\Domain\Event\DownloadableFileRemoved;
use App\Catalog\Domain\Event\DownloadableFileUpdated;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\ValueObject\DownloadableFile;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ProductDownloadableTest extends TestCase
{
    private ProductId $productId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->productId = ProductId::generate();
        $this->tenantId = TenantId::generate();
    }

    public function testAttachDownloadableFileSuccessfully(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('DIG-000001'),
            name: ProductName::fromString('Digital Ebook'),
            description: 'A digital ebook',
            shortDescription: 'Ebook',
            price: Money::of('19.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $product->attachDownloadableFile(
            filename: 'ebook.pdf',
            fileUrl: 'https://cdn.example.com/ebook.pdf',
            fileSizeBytes: 5242880,
            downloadLimit: 5
        );

        $this->assertTrue($product->hasDownloadableFile());
        $this->assertSame('ebook.pdf', $product->downloadableFile()->filename());

        $events = $product->pullDomainEvents();
        $this->assertCount(2, $events); // ProductCreated + DownloadableFileAttached
        $this->assertInstanceOf(DownloadableFileAttached::class, $events[1]);
    }

    public function testAttachDownloadableFileWithExpiration(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('SOF-000001'),
            name: ProductName::fromString('Trial Software'),
            description: 'Trial version',
            shortDescription: 'Software',
            price: Money::of('0.00', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $expiresAt = new \DateTimeImmutable('+30 days');
        $product->attachDownloadableFile(
            filename: 'trial.exe',
            fileUrl: 'https://cdn.example.com/trial.exe',
            fileSizeBytes: 52428800,
            downloadLimit: 3,
            expiresAt: $expiresAt
        );

        $this->assertTrue($product->downloadableFile()->hasExpiration());
        $this->assertSame($expiresAt, $product->downloadableFile()->expiresAt());
    }

    public function testAttachDownloadableFileToNonVirtualProductThrowsException(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('BOO-000001'),
            name: ProductName::fromString('Physical Book'),
            description: 'A physical book',
            shortDescription: 'Book',
            price: Money::of('29.99', 'USD'),
            categoryId: null,
            stock: Stock::create(100, true, false),
            type: ProductType::simple() // Not virtual
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only virtual products can have downloadable files');

        $product->attachDownloadableFile(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024
        );
    }

    public function testAttachDownloadableFileWhenAlreadyExistsThrowsException(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('DIG-000002'),
            name: ProductName::fromString('Digital Ebook'),
            description: 'A digital ebook',
            shortDescription: 'Ebook',
            price: Money::of('19.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $product->attachDownloadableFile(
            filename: 'ebook.pdf',
            fileUrl: 'https://cdn.example.com/ebook.pdf',
            fileSizeBytes: 5242880
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product already has a downloadable file');

        $product->attachDownloadableFile(
            filename: 'another.pdf',
            fileUrl: 'https://cdn.example.com/another.pdf',
            fileSizeBytes: 1024
        );
    }

    public function testUpdateDownloadableFileSuccessfully(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('SOF-000002'),
            name: ProductName::fromString('Software'),
            description: 'Software',
            shortDescription: 'Soft',
            price: Money::of('49.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $product->attachDownloadableFile(
            filename: 'software-v1.zip',
            fileUrl: 'https://cdn.example.com/software-v1.zip',
            fileSizeBytes: 104857600
        );

        $product->updateDownloadableFile(
            filename: 'software-v2.zip',
            fileUrl: 'https://cdn.example.com/software-v2.zip',
            fileSizeBytes: 209715200,
            downloadLimit: 10
        );

        $this->assertSame('software-v2.zip', $product->downloadableFile()->filename());
        $this->assertSame(209715200, $product->downloadableFile()->fileSizeBytes());

        $events = $product->pullDomainEvents();
        $this->assertCount(3, $events); // ProductCreated + FileAttached + FileUpdated
        $this->assertInstanceOf(DownloadableFileUpdated::class, $events[2]);
    }

    public function testUpdateDownloadableFileWhenNotExistsThrowsException(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('DIG-000003'),
            name: ProductName::fromString('Digital Ebook'),
            description: 'A digital ebook',
            shortDescription: 'Ebook',
            price: Money::of('19.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product does not have a downloadable file. Use attachDownloadableFile() first.');

        $product->updateDownloadableFile(
            filename: 'new.pdf',
            fileUrl: 'https://cdn.example.com/new.pdf',
            fileSizeBytes: 1024
        );
    }

    public function testRemoveDownloadableFileSuccessfully(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('DIG-000004'),
            name: ProductName::fromString('Digital Ebook'),
            description: 'A digital ebook',
            shortDescription: 'Ebook',
            price: Money::of('19.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $product->attachDownloadableFile(
            filename: 'ebook.pdf',
            fileUrl: 'https://cdn.example.com/ebook.pdf',
            fileSizeBytes: 5242880
        );

        $this->assertTrue($product->hasDownloadableFile());

        $product->removeDownloadableFile();

        $this->assertFalse($product->hasDownloadableFile());

        $events = $product->pullDomainEvents();
        $this->assertCount(3, $events); // ProductCreated + FileAttached + FileRemoved
        $this->assertInstanceOf(DownloadableFileRemoved::class, $events[2]);
    }

    public function testRemoveDownloadableFileWhenNotExistsThrowsException(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('DIG-000005'),
            name: ProductName::fromString('Digital Ebook'),
            description: 'A digital ebook',
            shortDescription: 'Ebook',
            price: Money::of('19.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product does not have a downloadable file. Use attachDownloadableFile() first.');

        $product->removeDownloadableFile();
    }

    public function testHasDownloadableFileReturnsFalseWhenNotSet(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('DIG-000006'),
            name: ProductName::fromString('Digital Ebook'),
            description: 'A digital ebook',
            shortDescription: 'Ebook',
            price: Money::of('19.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $this->assertFalse($product->hasDownloadableFile());
    }

    public function testDownloadableFileGetterReturnsNullWhenNotSet(): void
    {
        $product = Product::create(
            id: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('DIG-000007'),
            name: ProductName::fromString('Digital Ebook'),
            description: 'A digital ebook',
            shortDescription: 'Ebook',
            price: Money::of('19.99', 'USD'),
            categoryId: null,
            stock: Stock::create(999, false, false),
            type: ProductType::virtual()
        );

        $this->assertNull($product->downloadableFile());
    }
}
