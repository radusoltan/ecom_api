<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\ConfigureSubscriptionCommand;
use App\Catalog\Application\Command\ConfigureSubscriptionCommandHandler;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ConfigureSubscriptionCommandHandlerTest extends TestCase
{
    public function testItThrowsWhenProductNotFound(): void
    {
        $repo = $this->createStub(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);

        $handler = new ConfigureSubscriptionCommandHandler($repo);
        ($handler)(new ConfigureSubscriptionCommand(
            ProductId::generate(),
            SubscriptionInterval::MONTHLY,
            12,
            Money::of('0', 'EUR'),
        ));
    }

    public function testItConfiguresSubscription(): void
    {
        $product = Product::create(
            ProductId::generate(), TenantId::generate(), SKU::fromString('PRD-000001'),
            ProductName::fromString('Sub Product'), null, null,
            Money::of('9.99', 'EUR'), null, Stock::create(0), ProductType::subscription(),
        );

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);
        $repo->expects($this->once())->method('save');

        $handler = new ConfigureSubscriptionCommandHandler($repo);
        ($handler)(new ConfigureSubscriptionCommand(
            $product->id(),
            SubscriptionInterval::MONTHLY,
            12,
            Money::of('0', 'EUR'),
        ));
    }
}
