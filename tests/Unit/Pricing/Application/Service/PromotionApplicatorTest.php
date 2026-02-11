<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\Service\PromotionApplicator;
use App\Pricing\Application\Service\PromotionStackingServiceInterface;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class PromotionApplicatorTest extends TestCase
{
    private PromotionRepositoryInterface $promotionRepository;
    private PromotionStackingServiceInterface $promotionStackingService;
    private PromotionApplicator $service;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->createMock(PromotionRepositoryInterface::class);
        $this->promotionStackingService = $this->createMock(PromotionStackingServiceInterface::class);

        $this->service = new PromotionApplicator(
            $this->promotionRepository,
            $this->promotionStackingService
        );
    }

    public function testValidateCouponSuccess(): void
    {
        $tenantId = TenantId::generate();
        $promotion = $this->createActivePromotion($tenantId);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with(
                $this->callback(fn ($code) => $code->toString() === 'TESTCOUPON'),
                $tenantId
            )
            ->willReturn($promotion);

        $result = $this->service->validateCoupon('TESTCOUPON', $tenantId);

        $this->assertSame($promotion, $result);
    }

    public function testValidateCouponThrowsExceptionForInvalidCode(): void
    {
        $tenantId = TenantId::generate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid coupon code');

        $this->service->validateCoupon('INVALID', $tenantId);
    }

    public function testValidateCouponThrowsExceptionForInactivePromotion(): void
    {
        $tenantId = TenantId::generate();
        $promotion = $this->createInactivePromotion($tenantId);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->willReturn($promotion);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not active');

        $this->service->validateCoupon('TESTCOUPON', $tenantId);
    }

    public function testValidateCouponThrowsExceptionForExpiredPromotion(): void
    {
        $tenantId = TenantId::generate();
        $promotion = $this->createExpiredPromotion($tenantId);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->willReturn($promotion);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expired');

        $this->service->validateCoupon('TESTCOUPON', $tenantId);
    }

    public function testCanApplyToCartReturnsTrueForValidPromotion(): void
    {
        $cart = $this->createCartWithTotal(Money::fromScalars(10000, 'USD'));
        $promotion = $this->createActivePromotion($cart->tenantId());

        $result = $this->service->canApplyToCart(
            $promotion,
            $cart,
            Money::fromScalars(10000, 'USD')
        );

        $this->assertTrue($result);
    }

    public function testCanApplyToCartReturnsFalseForInactivePromotion(): void
    {
        $cart = $this->createCartWithTotal(Money::fromScalars(10000, 'USD'));
        $promotion = $this->createInactivePromotion($cart->tenantId());

        $result = $this->service->canApplyToCart(
            $promotion,
            $cart,
            Money::fromScalars(10000, 'USD')
        );

        $this->assertFalse($result);
    }

    public function testCanApplyToCartReturnsFalseWhenMinPurchaseNotMet(): void
    {
        $cart = $this->createCartWithTotal(Money::fromScalars(3000, 'USD'));
        $promotion = $this->createPromotionWithMinPurchase($cart->tenantId(), 5000); // $50 in cents

        $result = $this->service->canApplyToCart(
            $promotion,
            $cart,
            Money::fromScalars(3000, 'USD')
        );

        $this->assertFalse($result);
    }

    public function testCanApplyToCartReturnsTrueWhenMinPurchaseMet(): void
    {
        $cart = $this->createCartWithTotal(Money::fromScalars(10000, 'USD'));
        $promotion = $this->createPromotionWithMinPurchase($cart->tenantId(), 5000); // $50 in cents

        $result = $this->service->canApplyToCart(
            $promotion,
            $cart,
            Money::fromScalars(10000, 'USD')
        );

        $this->assertTrue($result);
    }

    public function testApplyPromotionReturnsDiscountDetails(): void
    {
        $cart = $this->createCartWithTotal(Money::fromScalars(10000, 'USD'));
        $promotion = $this->createActivePromotion($cart->tenantId());

        $result = $this->service->applyPromotion(
            $promotion,
            $cart,
            Money::fromScalars(10000, 'USD')
        );

        $this->assertSame($promotion->id()->toString(), $result->id);
        $this->assertSame('promotion', $result->type);
        $this->assertSame($promotion->name(), $result->name);
        $this->assertSame('cart', $result->scope);
    }

    public function testApplyPromotionThrowsExceptionWhenCannotApply(): void
    {
        $cart = $this->createCartWithTotal(Money::fromScalars(3000, 'USD'));
        $promotion = $this->createPromotionWithMinPurchase($cart->tenantId(), 5000); // $50 in cents

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be applied');

        $this->service->applyPromotion(
            $promotion,
            $cart,
            Money::fromScalars(3000, 'USD')
        );
    }

    public function testCalculateDiscountReturnsCorrectAmount(): void
    {
        $tenantId = TenantId::generate();
        $promotion = Promotion::create(
            PromotionId::generate(),
            $tenantId,
            'Test',
            PromotionType::cartRule(),
            Discount::percentage(10.0),
            100,
            null,
            []
        );

        $promotion->activate();

        $discount = $this->service->calculateDiscount(
            $promotion,
            Money::fromScalars(10000, 'USD')
        );

        $this->assertSame(1000, $discount->getAmount());
    }

    public function testValidateCouponStackSuccess(): void
    {
        $tenantId = TenantId::generate();
        $promotion1 = $this->createActivePromotion($tenantId, 'CODE1');
        $promotion2 = $this->createActivePromotion($tenantId, 'CODE2');

        $this->promotionRepository
            ->expects($this->exactly(2))
            ->method('findByCouponCode')
            ->willReturnOnConsecutiveCalls($promotion1, $promotion2);

        $this->promotionStackingService
            ->expects($this->once())
            ->method('canStack')
            ->with([$promotion1, $promotion2])
            ->willReturn(true);

        $result = $this->service->validateCouponStack(['CODE1', 'CODE2'], $tenantId);

        $this->assertCount(2, $result);
    }

    public function testValidateCouponStackThrowsExceptionWhenStackingNotAllowed(): void
    {
        $tenantId = TenantId::generate();
        $promotion1 = $this->createActivePromotion($tenantId, 'CODE1');
        $promotion2 = $this->createActivePromotion($tenantId, 'CODE2');

        $this->promotionRepository
            ->expects($this->exactly(2))
            ->method('findByCouponCode')
            ->willReturnOnConsecutiveCalls($promotion1, $promotion2);

        $this->promotionStackingService
            ->expects($this->once())
            ->method('canStack')
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be stacked');

        $this->service->validateCouponStack(['CODE1', 'CODE2'], $tenantId);
    }

    // Helper methods

    private function createCartWithTotal(Money $total): Cart
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
            $total
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

    private function createActivePromotion(TenantId $tenantId, string $couponCode = 'TESTCOUPON'): Promotion
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            $tenantId,
            'Test Promotion',
            PromotionType::coupon(),
            Discount::percentage(10.0),
            100,
            CouponCode::fromString($couponCode),
            []
        );

        $promotion->activate();

        return $promotion;
    }

    private function createInactivePromotion(TenantId $tenantId): Promotion
    {
        return Promotion::create(
            PromotionId::generate(),
            $tenantId,
            'Inactive Promotion',
            PromotionType::coupon(),
            Discount::percentage(10.0),
            100,
            CouponCode::fromString('TESTCOUPON'),
            []
        );
    }

    private function createExpiredPromotion(TenantId $tenantId): Promotion
    {
        $yesterday = new \DateTimeImmutable('-1 day');
        $twoDaysAgo = new \DateTimeImmutable('-2 days');

        $promotion = Promotion::create(
            PromotionId::generate(),
            $tenantId,
            'Expired Promotion',
            PromotionType::coupon(),
            Discount::percentage(10.0),
            100,
            CouponCode::fromString('TESTCOUPON'),
            [],
            [], // targetSegments
            $twoDaysAgo,
            $yesterday
        );

        $promotion->activate();

        return $promotion;
    }

    private function createPromotionWithMinPurchase(TenantId $tenantId, int $minPurchase): Promotion
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            $tenantId,
            'Min Purchase Promotion',
            PromotionType::cartRule(),
            Discount::percentage(10.0),
            100,
            null,
            ['min_purchase' => $minPurchase]
        );

        $promotion->activate();

        return $promotion;
    }
}
