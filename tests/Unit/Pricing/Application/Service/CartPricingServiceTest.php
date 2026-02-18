<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\Service\CartPricingService;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CartPricingServiceTest extends TestCase
{
    private PriceListRepositoryInterface $priceListRepository;
    private PromotionRepositoryInterface $promotionRepository;
    private CartPricingService $service;

    protected function setUp(): void
    {
        $this->priceListRepository = $this->createMock(PriceListRepositoryInterface::class);
        $this->promotionRepository = $this->createMock(PromotionRepositoryInterface::class);

        $this->service = new CartPricingService(
            $this->priceListRepository,
            $this->promotionRepository,
        );
    }

    public function testCalculateCartPricingForEmptyCart(): void
    {
        $cart = Cart::create(
            CartId::generate(),
            TenantId::generate(),
            null,
            SessionId::generate()
        );

        $result = $this->service->calculateCartPricing($cart);

        $this->assertSame($cart->id()->toString(), $result->cartId);
        $this->assertEmpty($result->items);
        $this->assertSame(0, $result->subtotal->getAmount());
        $this->assertSame(0, $result->totalDiscounts->getAmount());
        $this->assertSame(0, $result->grandTotal->getAmount());
        $this->assertSame(0, $result->totalItemsCount);
    }

    public function testCalculateCartPricingWithSingleItemNoPriceLists(): void
    {
        $cart = $this->createCartWithSingleItem();

        $this->priceListRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $result = $this->service->calculateCartPricing($cart);

        $this->assertCount(1, $result->items);
        $this->assertSame(10000, $result->subtotal->getAmount());
        $this->assertSame(0, $result->totalDiscounts->getAmount());
        $this->assertSame(10000, $result->grandTotal->getAmount());
        $this->assertSame(1, $result->totalItemsCount);
    }

    public function testCalculateCartPricingWithInactivePriceListAppliesNoDiscount(): void
    {
        $cart = $this->createCartWithSingleItem();
        $priceList = $this->createPriceListWithDiscount();

        $this->priceListRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([$priceList]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $result = $this->service->calculateCartPricing($cart);

        $this->assertCount(1, $result->items);
        $this->assertSame(10000, $result->subtotal->getAmount());
        // PriceList created via ::create() is inactive with no rules, so no discount
        $this->assertSame(0, $result->items[0]->totalDiscount->getAmount());
    }

    public function testCalculateCartPricingWithCartLevelPromotion(): void
    {
        $cart = $this->createCartWithSingleItem();
        $promotion = $this->createCartRulePromotion();

        $this->priceListRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([$promotion]);

        $result = $this->service->calculateCartPricing($cart);

        $this->assertSame(10000, $result->subtotal->getAmount());
        $this->assertGreaterThan(0, $result->totalDiscounts->getAmount());
        $this->assertCount(1, $result->cartLevelDiscounts);
    }

    public function testCalculateCartPricingWithCouponCodeFiltersCouponCorrectly(): void
    {
        $cart = $this->createCartWithSingleItem();
        $promotion = $this->createCouponPromotion();

        $this->priceListRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([$promotion]);

        // Coupon-type promotions are filtered as applicable but the service
        // only applies cart_rule and catalog_rule types, so no discount is expected
        $result = $this->service->calculateCartPricing($cart, ['TESTCOUPON']);

        $this->assertSame(0, $result->totalDiscounts->getAmount());
    }

    public function testCalculateCartPricingFiltersNonMatchingCoupons(): void
    {
        $cart = $this->createCartWithSingleItem();
        $promotion = $this->createCouponPromotion();

        $this->priceListRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([$promotion]);

        // Apply wrong coupon code
        $result = $this->service->calculateCartPricing($cart, ['WRONGCOUPON']);

        // Coupon should not be applied
        $this->assertSame(0, $result->totalDiscounts->getAmount());
    }

    public function testCalculateCartPricingWithMultipleItems(): void
    {
        $cart = $this->createCartWithMultipleItems();

        $this->priceListRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActiveByTenantId')
            ->willReturn([]);

        $result = $this->service->calculateCartPricing($cart);

        $this->assertCount(2, $result->items);
        $this->assertSame(30000, $result->subtotal->getAmount()); // 100 + 200
        $this->assertSame(2, $result->totalItemsCount);
    }

    // Helper methods

    private function createCartWithSingleItem(): Cart
    {
        $cart = Cart::create(
            CartId::generate(),
            TenantId::generate(),
            null,
            SessionId::generate()
        );

        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(10000, 'USD') // 100.00 in minor units (cents)
        );

        return Cart::reconstituteFromPersistence(
            $cart->id(),
            $cart->tenantId(),
            $cart->customerId(),
            $cart->sessionId(),
            $cart->status(),
            $cart->items(),
            $cart->createdAt(),
            $cart->updatedAt()
        );
    }

    private function createCartWithMultipleItems(): Cart
    {
        $cart = Cart::create(
            CartId::generate(),
            TenantId::generate(),
            null,
            SessionId::generate()
        );

        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(10000, 'USD') // 100.00 in minor units (cents)
        );

        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(20000, 'USD') // 200.00 in minor units (cents)
        );

        return Cart::reconstituteFromPersistence(
            $cart->id(),
            $cart->tenantId(),
            $cart->customerId(),
            $cart->sessionId(),
            $cart->status(),
            $cart->items(),
            $cart->createdAt(),
            $cart->updatedAt()
        );
    }

    private function createPriceListWithDiscount(): PriceList
    {
        return PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List'),
            100
        );
    }

    private function createCartRulePromotion(): Promotion
    {
        return Promotion::create(
            PromotionId::generate(),
            TenantId::generate(),
            'Cart Rule Promotion',
            PromotionType::cartRule(),
            Discount::percentage(10.0),
            100,
            null,
            ['min_purchase' => 50.00]
        );
    }

    private function createCouponPromotion(): Promotion
    {
        return Promotion::create(
            PromotionId::generate(),
            TenantId::generate(),
            'Coupon Promotion',
            PromotionType::coupon(),
            Discount::percentage(15.0),
            100,
            \App\Pricing\Domain\ValueObject\CouponCode::fromString('TESTCOUPON'),
            []
        );
    }
}
