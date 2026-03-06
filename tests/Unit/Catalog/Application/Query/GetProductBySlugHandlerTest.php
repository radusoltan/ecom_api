<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetProductBySlug;
use App\Catalog\Application\Query\GetProductBySlugHandler;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetProductBySlugHandlerTest extends TestCase
{
    public function testItReturnsProductBySlug(): void
    {
        $tenantId = TenantId::generate();
        $product = Product::create(ProductId::generate(), $tenantId, SKU::fromString('PRD-000001'), ProductName::fromString('Widget'), null, null, Money::of('10.00', 'EUR'), null, Stock::create(10));

        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn($product);

        $result = (new GetProductBySlugHandler($repo))(new GetProductBySlug($tenantId, Slug::fromString('widget')));

        self::assertSame('Widget', $result->name()->value());
    }

    public function testItThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(null);

        $this->expectException(ProductNotFoundException::class);

        (new GetProductBySlugHandler($repo))(new GetProductBySlug(TenantId::generate(), Slug::fromString('nonexistent')));
    }
}
