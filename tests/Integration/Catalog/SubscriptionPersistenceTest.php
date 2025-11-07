<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SubscriptionPersistenceTest extends KernelTestCase
{
    public function testSubscriptionDataPersistsAndReconstitutes(): void
    {
        self::bootKernel();

        // Create product with subscription
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::generate(),
            sku: SKU::fromString('SUB-123456'),
            name: ProductName::fromString('Monthly Box'),
            description: 'Subscription box',
            shortDescription: 'Box',
            price: Money::fromScalars(2999, 'USD'),
            categoryId: CategoryId::generate(),
            stock: Stock::create(100, true, false),
            type: ProductType::subscription()
        );

        $trialEnd = new \DateTimeImmutable('+30 days');
        $product->configureSubscription(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(500, 'USD'),
            $trialEnd
        );

        // Convert to entity
        $entity = ProductEntity::fromDomainModel($product);

        // Verify entity has subscription data
        $this->assertNotNull($entity->getId());

        // Convert back to domain model
        $reconstituted = $entity->toDomainModel();

        // Verify subscription was preserved
        $this->assertTrue($reconstituted->hasSubscription());
        $subscription = $reconstituted->subscription();

        $this->assertEquals(SubscriptionInterval::MONTHLY, $subscription->interval());
        $this->assertEquals(12, $subscription->billingCycles());
        $this->assertEquals(500, $subscription->setupFee()->getAmount());
        $this->assertEquals('USD', $subscription->setupFee()->getCurrency()->getCurrencyCode());
        $this->assertTrue($subscription->hasTrial());
        $this->assertNotNull($subscription->trialPeriodEnd());
    }

    public function testProductWithoutSubscriptionReconstitutesCorrectly(): void
    {
        self::bootKernel();

        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::generate(),
            sku: SKU::fromString('SIM-123456'),
            name: ProductName::fromString('Simple Product'),
            description: 'Simple',
            shortDescription: 'Simple',
            price: Money::fromScalars(1999, 'USD'),
            categoryId: null,
            stock: Stock::create(50, true, false),
            type: ProductType::simple()
        );

        $entity = ProductEntity::fromDomainModel($product);
        $reconstituted = $entity->toDomainModel();

        $this->assertFalse($reconstituted->hasSubscription());
        $this->assertNull($reconstituted->subscription());
    }
}
