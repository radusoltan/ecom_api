<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\UpdateProduct;
use App\Catalog\Application\Command\UpdateProductHandler;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateProductHandler::class)]
final class UpdateProductHandlerTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private UpdateProductHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new UpdateProductHandler($this->productRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itUpdatesProductWhenFoundAndTenantMatches(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $productId = ProductId::generate();
        $command = new UpdateProduct(
            id: $productId,
            tenantId: $tenantId,
            name: ProductName::fromString('Updated Name'),
            description: 'Updated description',
            shortDescription: null,
            price: Money::of('49.99', 'EUR'),
            categoryId: null,
            isFeatured: true,
        );

        $product = Product::create(
            id: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('UPD-000001'),
            name: ProductName::fromString('Original Name'),
            description: null,
            shortDescription: null,
            price: Money::of('19.99', 'EUR'),
            categoryId: null,
            stock: Stock::create(5, true, false),
        );

        $this->productRepository
            ->expects(self::once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->productRepository
            ->expects(self::once())
            ->method('save')
            ->with($product);

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------
    // Error paths
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenProductNotFound(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $productId = ProductId::generate();
        $command = new UpdateProduct(
            id: $productId,
            tenantId: $tenantId,
            name: ProductName::fromString('Name'),
            description: null,
            shortDescription: null,
            price: Money::of('1.00', 'EUR'),
            categoryId: null,
            isFeatured: false,
        );

        $this->productRepository->method('findById')->willReturn(null);
        $this->productRepository->expects(self::never())->method('save');

        $this->expectException(ProductNotFoundException::class);

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenTenantDoesNotOwnProduct(): void
    {
        $ownerTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $differentTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000002');
        $productId = ProductId::generate();

        $command = new UpdateProduct(
            id: $productId,
            tenantId: $differentTenantId,
            name: ProductName::fromString('Hacked Name'),
            description: null,
            shortDescription: null,
            price: Money::of('0.01', 'EUR'),
            categoryId: null,
            isFeatured: false,
        );

        $product = Product::create(
            id: $productId,
            tenantId: $ownerTenantId,
            sku: SKU::fromString('OWN-000001'),
            name: ProductName::fromString('Owner Product'),
            description: null,
            shortDescription: null,
            price: Money::of('100.00', 'EUR'),
            categoryId: null,
            stock: Stock::create(0, false, false),
        );

        $this->productRepository->method('findById')->willReturn($product);
        $this->productRepository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot update product from different tenant');

        ($this->handler)($command);
    }
}
