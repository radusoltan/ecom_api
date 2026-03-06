<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\CreateProduct;
use App\Catalog\Application\Command\CreateProductHandler;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateProductHandler::class)]
final class CreateProductHandlerTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private CreateProductHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new CreateProductHandler($this->productRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesProductWhenSkuIsUnique(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $productId = ProductId::generate();
        $sku = SKU::fromString('SKU-000001');
        $command = new CreateProduct(
            id: $productId,
            tenantId: $tenantId,
            sku: $sku,
            name: ProductName::fromString('Test Product'),
            description: 'A test product',
            shortDescription: 'Short',
            price: Money::of('29.99', 'EUR'),
            categoryId: null,
            stockQuantity: 10,
            trackInventory: true,
            allowBackorder: false,
            isFeatured: false,
        );

        $this->productRepository
            ->expects(self::once())
            ->method('findBySKU')
            ->with($tenantId, $sku)
            ->willReturn(null);

        $this->productRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Product::class));

        ($this->handler)($command);
    }

    #[Test]
    public function itCreatesProductWithCategoryId(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $categoryId = CategoryId::generate();
        $command = new CreateProduct(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: SKU::fromString('SKU-000002'),
            name: ProductName::fromString('Categorised Product'),
            description: null,
            shortDescription: null,
            price: Money::of('9.99', 'USD'),
            categoryId: $categoryId,
            stockQuantity: 0,
        );

        $this->productRepository->method('findBySKU')->willReturn(null);
        $this->productRepository->expects(self::once())->method('save');

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------
    // Error paths
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenSkuAlreadyExists(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $sku = SKU::fromString('DUP-000001');
        $command = new CreateProduct(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: $sku,
            name: ProductName::fromString('Duplicate Product'),
            description: null,
            shortDescription: null,
            price: Money::of('5.00', 'EUR'),
            categoryId: null,
            stockQuantity: 0,
        );

        $existingProduct = $this->createMock(Product::class);

        $this->productRepository
            ->method('findBySKU')
            ->willReturn($existingProduct);

        $this->productRepository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product with this SKU already exists');

        ($this->handler)($command);
    }
}
