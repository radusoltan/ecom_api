<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query\ValidateCoupon;

use App\Pricing\Application\Query\ValidateCoupon\ValidateCouponQuery;
use App\Pricing\Application\Query\ValidateCoupon\ValidateCouponQueryHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ValidateCouponQueryHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $promotionRepository;
    private ValidateCouponQueryHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->createMock(PromotionRepositoryInterface::class);
        $this->handler = new ValidateCouponQueryHandler($this->promotionRepository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testValidateCouponSuccessfully(): void
    {
        // Given
        $couponCode = CouponCode::fromString('SUMMER20');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Summer Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(20.0),
            couponCode: $couponCode
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertTrue($result->valid);
        $this->assertNotNull($result->promotion);
        $this->assertEquals('Summer Sale', $result->promotion->name);
        $this->assertNotNull($result->discountAmount);
        $this->assertEquals('2000', $result->discountAmount['amount']); // 20% of 10000 cents
        $this->assertStringContainsString('applied successfully', $result->message);
    }

    public function testValidateCouponNotFound(): void
    {
        // Given
        $couponCode = CouponCode::fromString('INVALID');
        $cartTotal = Money::of('100.00', 'USD');

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn(null);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertFalse($result->valid);
        $this->assertNull($result->promotion);
        $this->assertNull($result->discountAmount);
        $this->assertEquals('Coupon code not found', $result->message);
    }

    public function testValidateCouponNotActive(): void
    {
        // Given
        $couponCode = CouponCode::fromString('INACTIVE');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Inactive Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(15.0),
            couponCode: $couponCode
        );
        // Don't activate

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertFalse($result->valid);
        $this->assertEquals('Coupon is not active', $result->message);
    }

    public function testValidateCouponExpired(): void
    {
        // Given
        $couponCode = CouponCode::fromString('EXPIRED');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Expired Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(10.0),
            couponCode: $couponCode,
            validFrom: new \DateTimeImmutable('2023-01-01'),
            validTo: new \DateTimeImmutable('2023-12-31')
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertFalse($result->valid);
        $this->assertEquals('Coupon has expired', $result->message);
    }

    public function testValidateCouponNotYetValid(): void
    {
        // Given
        $couponCode = CouponCode::fromString('FUTURE');
        $cartTotal = Money::of('100.00', 'USD');
        $validFrom = new \DateTimeImmutable('+1 month');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Future Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(25.0),
            couponCode: $couponCode,
            validFrom: $validFrom,
            validTo: new \DateTimeImmutable('+2 months')
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertFalse($result->valid);
        $this->assertStringContainsString('not yet valid', $result->message);
        $this->assertStringContainsString($validFrom->format('Y-m-d'), $result->message);
    }

    public function testValidateCouponMinimumPurchaseNotMet(): void
    {
        // Given
        $couponCode = CouponCode::fromString('BIGSPEND');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Big Spender',
            type: PromotionType::coupon(),
            discount: Discount::percentage(30.0),
            couponCode: $couponCode,
            conditions: ['min_purchase_amount' => 200.00]
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Minimum purchase amount', $result->message);
    }

    public function testValidateCouponWithMaxDiscountLimit(): void
    {
        // Given
        $couponCode = CouponCode::fromString('LIMITED');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Limited Discount',
            type: PromotionType::coupon(),
            discount: Discount::percentage(50.0),
            couponCode: $couponCode,
            conditions: ['max_discount_amount' => 25.00]
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertTrue($result->valid);
        $this->assertEquals('2500', $result->discountAmount['amount']); // Capped at $25
    }

    public function testValidateCouponUsageLimitReached(): void
    {
        // Given
        $couponCode = CouponCode::fromString('LIMITED');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Limited Uses',
            type: PromotionType::coupon(),
            discount: Discount::percentage(10.0),
            couponCode: $couponCode,
            conditions: [
                'max_uses' => 100,
                'current_uses' => 100,
            ]
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertFalse($result->valid);
        $this->assertStringContainsString('usage limit', $result->message);
    }

    public function testValidateCouponWithFixedDiscount(): void
    {
        // Given
        $couponCode = CouponCode::fromString('FIXED15');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Fixed Discount',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(1500, 'USD'),
            couponCode: $couponCode
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertTrue($result->valid);
        $this->assertEquals('1500', $result->discountAmount['amount']); // $15 fixed
    }

    public function testValidateCouponMinimumPurchaseMet(): void
    {
        // Given
        $couponCode = CouponCode::fromString('BIGSPEND');
        $cartTotal = Money::of('250.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Big Spender',
            type: PromotionType::coupon(),
            discount: Discount::percentage(30.0),
            couponCode: $couponCode,
            conditions: ['min_purchase_amount' => 200.00]
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertTrue($result->valid);
        $this->assertNotNull($result->discountAmount);
    }

    public function testValidateCouponWithinValidityPeriod(): void
    {
        // Given
        $couponCode = CouponCode::fromString('CURRENT');
        $cartTotal = Money::of('100.00', 'USD');
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Current Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(15.0),
            couponCode: $couponCode,
            validFrom: new \DateTimeImmutable('-1 month'),
            validTo: new \DateTimeImmutable('+1 month')
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($couponCode, $this->tenantId)
            ->willReturn($promotion);

        $query = new ValidateCouponQuery($couponCode, $cartTotal, $this->tenantId);

        // When
        $result = ($this->handler)($query);

        // Then
        $this->assertTrue($result->valid);
        $this->assertNotNull($result->promotion);
    }
}
