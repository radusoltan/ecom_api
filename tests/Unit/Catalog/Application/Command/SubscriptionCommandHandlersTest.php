<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\ConfigureSubscriptionCommand;
use App\Catalog\Application\Command\ConfigureSubscriptionCommandHandler;
use App\Catalog\Application\Command\UpdateSubscriptionCommand;
use App\Catalog\Application\Command\UpdateSubscriptionCommandHandler;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigureSubscriptionCommandHandler::class)]
#[CoversClass(UpdateSubscriptionCommandHandler::class)]
final class SubscriptionCommandHandlersTest extends TestCase
{
    private ProductId $productId;
    private SubscriptionInterval $interval;
    private Money $setupFee;

    protected function setUp(): void
    {
        $this->productId = ProductId::generate();
        $this->interval = SubscriptionInterval::MONTHLY;
        $this->setupFee = Money::fromScalars(0, 'USD');
    }

    // ---------------------------------------------------------------
    // ConfigureSubscriptionCommandHandler
    // ---------------------------------------------------------------

    #[Test]
    public function configureSubscriptionThrowsWhenProductNotFound(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new ConfigureSubscriptionCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product with ID');

        $handler(new ConfigureSubscriptionCommand(
            productId: $this->productId,
            interval: $this->interval,
            billingCycles: 12,
            setupFee: $this->setupFee,
        ));
    }

    #[Test]
    public function configureSubscriptionConfiguresAndSavesProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->expects(self::once())
            ->method('configureSubscription')
            ->with($this->interval, 12, $this->setupFee, null);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);
        $repo->expects(self::once())->method('save')->with($product);

        $handler = new ConfigureSubscriptionCommandHandler($repo);
        $handler(new ConfigureSubscriptionCommand(
            productId: $this->productId,
            interval: $this->interval,
            billingCycles: 12,
            setupFee: $this->setupFee,
        ));
    }

    #[Test]
    public function configureSubscriptionPassesTrialPeriodEnd(): void
    {
        $trialEnd = new \DateTimeImmutable('+30 days');

        $product = $this->createMock(Product::class);
        $product->expects(self::once())
            ->method('configureSubscription')
            ->with($this->interval, 0, $this->setupFee, $trialEnd);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);

        $handler = new ConfigureSubscriptionCommandHandler($repo);
        $handler(new ConfigureSubscriptionCommand(
            productId: $this->productId,
            interval: $this->interval,
            billingCycles: 0,
            setupFee: $this->setupFee,
            trialPeriodEnd: $trialEnd,
        ));
    }

    // ---------------------------------------------------------------
    // UpdateSubscriptionCommandHandler
    // ---------------------------------------------------------------

    #[Test]
    public function updateSubscriptionThrowsWhenProductNotFound(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new UpdateSubscriptionCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product with ID');

        $handler(new UpdateSubscriptionCommand(
            productId: $this->productId,
            interval: $this->interval,
            billingCycles: 6,
            setupFee: $this->setupFee,
        ));
    }

    #[Test]
    public function updateSubscriptionUpdatesAndSavesProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->expects(self::once())
            ->method('updateSubscription')
            ->with($this->interval, 6, $this->setupFee, null);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);
        $repo->expects(self::once())->method('save')->with($product);

        $handler = new UpdateSubscriptionCommandHandler($repo);
        $handler(new UpdateSubscriptionCommand(
            productId: $this->productId,
            interval: $this->interval,
            billingCycles: 6,
            setupFee: $this->setupFee,
        ));
    }

    #[Test]
    public function updateSubscriptionPassesTrialPeriodEnd(): void
    {
        $trialEnd = new \DateTimeImmutable('+60 days');

        $product = $this->createMock(Product::class);
        $product->expects(self::once())
            ->method('updateSubscription')
            ->with($this->interval, 3, $this->setupFee, $trialEnd);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);

        $handler = new UpdateSubscriptionCommandHandler($repo);
        $handler(new UpdateSubscriptionCommand(
            productId: $this->productId,
            interval: $this->interval,
            billingCycles: 3,
            setupFee: $this->setupFee,
            trialPeriodEnd: $trialEnd,
        ));
    }
}
