<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\RemoveBundleCommand;
use App\Catalog\Application\Command\RemoveBundleCommandHandler;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class RemoveBundleCommandHandlerTest extends TestCase
{
    public function testItThrowsWhenProductNotFound(): void
    {
        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);

        $handler = new RemoveBundleCommandHandler($repo);
        ($handler)(new RemoveBundleCommand(ProductId::generate()));
    }

    public function testItRemovesBundle(): void
    {
        $product = Product::create(
            ProductId::generate(), TenantId::generate(), SKU::fromString('PRD-000001'),
            ProductName::fromString('Bundle Product'), null, null,
            Money::of('99.99', 'USD'), null, Stock::create(0), ProductType::bundle(),
        );
        $product->createBundle([
            BundleItem::create(ProductId::generate(), 1, Money::of('50.00', 'USD')),
        ], 10.0);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);
        $repo->expects($this->once())->method('save');

        $handler = new RemoveBundleCommandHandler($repo);
        ($handler)(new RemoveBundleCommand($product->id()));

        self::assertNull($product->bundle());
    }
}
