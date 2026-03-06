<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetProducts;
use App\Catalog\Application\Query\GetProductsHandler;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetProductsHandlerTest extends TestCase
{
    public function testItReturnsProductsForTenant(): void
    {
        $tenantId = TenantId::generate();
        $product = Product::create(ProductId::generate(), $tenantId, SKU::fromString('PRD-000001'), ProductName::fromString('Widget'), null, null, Money::of('10.00', 'EUR'), null, Stock::create(10));

        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findByTenant')->willReturn([$product]);

        $result = (new GetProductsHandler($repo))(new GetProducts($tenantId));

        self::assertCount(1, $result);
    }

    public function testItReturnsEmptyWhenNoProducts(): void
    {
        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findByTenant')->willReturn([]);

        $result = (new GetProductsHandler($repo))(new GetProducts(TenantId::generate()));

        self::assertSame([], $result);
    }

    public function testItPassesLimitAndOffset(): void
    {
        $tenantId = TenantId::generate();
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->expects($this->once())->method('findByTenant')->with($tenantId, 50, 10)->willReturn([]);

        (new GetProductsHandler($repo))(new GetProducts($tenantId, 50, 10));
    }
}
