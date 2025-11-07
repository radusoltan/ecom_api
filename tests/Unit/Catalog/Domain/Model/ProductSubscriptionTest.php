<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ProductSubscriptionTest extends TestCase
{
    private Product $subscriptionProduct;

    protected function setUp(): void
    {
        $this->subscriptionProduct = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::generate(),
            sku: SKU::fromString('SUB-000001'),
            name: ProductName::fromString('Monthly Box'),
            description: 'Monthly subscription box',
            shortDescription: 'Subscription',
            price: Money::fromScalars(2999, 'USD'),
            categoryId: CategoryId::generate(),
            stock: Stock::create(100, true, false),
            type: ProductType::subscription()
        );
    }

    public function testConfigureSubscriptionSuccessfully(): void
    {
        $this->subscriptionProduct->configureSubscription(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(500, 'USD'),
            null
        );

        $this->assertTrue($this->subscriptionProduct->hasSubscription());
        $subscription = $this->subscriptionProduct->subscription();

        $this->assertEquals(SubscriptionInterval::MONTHLY, $subscription->interval());
        $this->assertEquals(12, $subscription->billingCycles());
        $this->assertEquals(500, $subscription->setupFee()->getAmount());
    }

    public function testConfigureSubscriptionWithTrial(): void
    {
        $trialEnd = new \DateTimeImmutable('+30 days');

        $this->subscriptionProduct->configureSubscription(
            SubscriptionInterval::MONTHLY,
            0, // infinite
            Money::fromScalars(0, 'USD'),
            $trialEnd
        );

        $subscription = $this->subscriptionProduct->subscription();

        $this->assertTrue($subscription->isInfinite());
        $this->assertTrue($subscription->hasTrial());
        $this->assertTrue($subscription->isTrialActive());
    }

    public function testConfigureSubscriptionOnNonSubscriptionProductThrows(): void
    {
        $simpleProduct = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::generate(),
            sku: SKU::fromString('SIM-000001'),
            name: ProductName::fromString('Simple Product'),
            description: 'Simple product',
            shortDescription: 'Simple',
            price: Money::fromScalars(1999, 'USD'),
            categoryId: null,
            stock: Stock::create(100, true, false),
            type: ProductType::simple()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only subscription products can have subscription configuration');

        $simpleProduct->configureSubscription(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD')
        );
    }

    public function testConfigureSubscriptionTwiceThrows(): void
    {
        $this->subscriptionProduct->configureSubscription(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD')
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has a subscription configuration');

        $this->subscriptionProduct->configureSubscription(
            SubscriptionInterval::WEEKLY,
            52,
            Money::fromScalars(0, 'USD')
        );
    }

    public function testUpdateSubscriptionSuccessfully(): void
    {
        // Configure first
        $this->subscriptionProduct->configureSubscription(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(500, 'USD')
        );

        // Update
        $this->subscriptionProduct->updateSubscription(
            SubscriptionInterval::YEARLY,
            5,
            Money::fromScalars(1000, 'USD')
        );

        $subscription = $this->subscriptionProduct->subscription();

        $this->assertEquals(SubscriptionInterval::YEARLY, $subscription->interval());
        $this->assertEquals(5, $subscription->billingCycles());
        $this->assertEquals(1000, $subscription->setupFee()->getAmount());
    }

    public function testUpdateSubscriptionWithoutConfigureThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not have a subscription configuration');

        $this->subscriptionProduct->updateSubscription(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD')
        );
    }

    public function testRemoveSubscriptionSuccessfully(): void
    {
        $this->subscriptionProduct->configureSubscription(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD')
        );

        $this->assertTrue($this->subscriptionProduct->hasSubscription());

        $this->subscriptionProduct->removeSubscription();

        $this->assertFalse($this->subscriptionProduct->hasSubscription());
        $this->assertNull($this->subscriptionProduct->subscription());
    }

    public function testRemoveSubscriptionWithoutConfigureThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not have a subscription configuration');

        $this->subscriptionProduct->removeSubscription();
    }

    public function testHasSubscriptionReturnsFalseInitially(): void
    {
        $this->assertFalse($this->subscriptionProduct->hasSubscription());
    }
}
