<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetProductById;
use App\Catalog\Application\Query\GetProductByIdHandler;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetProductByIdHandlerTest extends TestCase
{
    public function testItReturnsProductWhenFound(): void
    {
        $tenantId = TenantId::generate();
        $productId = ProductId::generate();
        $product = Product::create($productId, $tenantId, SKU::fromString('PRD-000001'), ProductName::fromString('Widget'), 'desc', null, Money::of('10.00', 'EUR'), null, Stock::create(100));

        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);

        $result = (new GetProductByIdHandler($repo))(new GetProductById($tenantId, $productId));

        self::assertTrue($result->id()->equals($productId));
    }

    public function testItThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(ProductNotFoundException::class);

        (new GetProductByIdHandler($repo))(new GetProductById(TenantId::generate(), ProductId::generate()));
    }

    public function testItThrowsWhenTenantMismatch(): void
    {
        $productId = ProductId::generate();
        $product = Product::create($productId, TenantId::generate(), SKU::fromString('PRD-000002'), ProductName::fromString('Widget'), null, null, Money::of('5.00', 'EUR'), null, Stock::create(10));

        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);

        $this->expectException(ProductNotFoundException::class);

        (new GetProductByIdHandler($repo))(new GetProductById(TenantId::generate(), $productId));
    }
}
