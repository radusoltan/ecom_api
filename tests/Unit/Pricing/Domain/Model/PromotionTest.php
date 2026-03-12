<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Pricing\Domain\Event\PromotionActivated;
use App\Pricing\Domain\Event\PromotionCreated;
use App\Pricing\Domain\Event\PromotionDeactivated;
use App\Pricing\Domain\Event\PromotionUpdated;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class PromotionTest extends TestCase
{
    private TenantId $tenantId;
    private PromotionId $promotionId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->promotionId = PromotionId::generate();
    }

    public function testCreatePromotion(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Summer Sale',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            priority: 100
        );

        $this->assertTrue($promotion->id()->equals($this->promotionId));
        $this->assertTrue($promotion->tenantId()->equals($this->tenantId));
        $this->assertSame('Summer Sale', $promotion->name());
        $this->assertTrue($promotion->type()->isCartRule());
        $this->assertTrue($promotion->discount()->type()->isPercentage());
        $this->assertSame(20.0, $promotion->discount()->value());
        $this->assertSame(100, $promotion->priority());
        $this->assertFalse($promotion->isActive());
        $this->assertNull($promotion->couponCode());
        $this->assertSame([], $promotion->conditions());

        $events = $promotion->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PromotionCreated::class, $events[0]);
    }

    public function testCreatePromotionWithCouponCode(): void
    {
        $couponCode = CouponCode::fromString('SAVE20');

        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Coupon Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(20.0),
            couponCode: $couponCode
        );

        $this->assertTrue($promotion->type()->isCoupon());
        $this->assertNotNull($promotion->couponCode());
        $this->assertTrue($promotion->couponCode()->equals($couponCode));
    }

    public function testCreateCouponTypeRequiresCouponCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon code is required for coupon type promotions');

        Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Invalid Coupon',
            type: PromotionType::coupon(),
            discount: Discount::percentage(20.0)
        );
    }

    public function testNameValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion name must be between 3 and 100 characters');

        Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'AB', // Too short
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0)
        );
    }

    public function testPriorityValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion priority must be between 1 and 1000');

        Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Invalid Priority',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            priority: 1001 // Too high
        );
    }

    public function testActivatePromotion(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0)
        );

        $promotion->popEvents(); // Clear creation event

        $promotion->activate();

        $this->assertTrue($promotion->isActive());

        $events = $promotion->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PromotionActivated::class, $events[0]);
    }

    public function testActivateAlreadyActivePromotion(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0)
        );

        $promotion->activate();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Promotion ".*" is already active/');

        $promotion->activate();
    }

    public function testDeactivatePromotion(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0)
        );

        $promotion->activate();
        $promotion->popEvents(); // Clear previous events

        $promotion->deactivate();

        $this->assertFalse($promotion->isActive());

        $events = $promotion->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PromotionDeactivated::class, $events[0]);
    }

    public function testDeactivateAlreadyInactivePromotion(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Promotion ".*" is already inactive/');

        $promotion->deactivate();
    }

    public function testUpdatePromotion(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Old Name',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            priority: 100
        );

        $promotion->popEvents(); // Clear creation event

        $newDiscount = Discount::percentage(30.0);
        $promotion->update(
            name: 'New Name',
            discount: $newDiscount,
            priority: 200,
            conditions: ['min_purchase' => 100],
            validFrom: null,
            validTo: null
        );

        $this->assertSame('New Name', $promotion->name());
        $this->assertSame(30.0, $promotion->discount()->value());
        $this->assertSame(200, $promotion->priority());
        $this->assertSame(['min_purchase' => 100], $promotion->conditions());

        $events = $promotion->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PromotionUpdated::class, $events[0]);
    }

    public function testIsApplicableForActivePromotion(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-12-31');

        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            validFrom: $validFrom,
            validTo: $validTo
        );

        $promotion->activate();

        $testDate = new \DateTimeImmutable('2025-06-15');
        $this->assertTrue($promotion->isApplicable($testDate));
    }

    public function testIsApplicableForInactivePromotion(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0)
        );

        $testDate = new \DateTimeImmutable();
        $this->assertFalse($promotion->isApplicable($testDate));
    }

    public function testIsApplicableForDateOutsideValidityPeriod(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-12-31');

        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            validFrom: $validFrom,
            validTo: $validTo
        );

        $promotion->activate();

        $testDate = new \DateTimeImmutable('2026-01-15'); // After validTo
        $this->assertFalse($promotion->isApplicable($testDate));
    }

    public function testReconstituteFromPersistence(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-01-02 15:00:00');
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-12-31');
        $couponCode = CouponCode::fromString('SAVE20');

        $promotion = Promotion::reconstituteFromPersistence(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Reconstituted Promotion',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(5000),
            priority: 150,
            isActive: true,
            couponCode: $couponCode,
            conditions: ['category' => 'electronics'],
            targetSegments: [],
            validFrom: $validFrom,
            validTo: $validTo,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertTrue($promotion->id()->equals($this->promotionId));
        $this->assertTrue($promotion->tenantId()->equals($this->tenantId));
        $this->assertSame('Reconstituted Promotion', $promotion->name());
        $this->assertTrue($promotion->type()->isCoupon());
        $this->assertSame(50.0, $promotion->discount()->value());
        $this->assertSame(150, $promotion->priority());
        $this->assertTrue($promotion->isActive());
        $this->assertTrue($promotion->couponCode()->equals($couponCode));
        $this->assertSame(['category' => 'electronics'], $promotion->conditions());
        $this->assertSame($validFrom, $promotion->validFrom());
        $this->assertSame($validTo, $promotion->validTo());
        $this->assertSame($createdAt, $promotion->createdAt());
        $this->assertSame($updatedAt, $promotion->updatedAt());

        // Reconstituted aggregates should not have events
        $this->assertCount(0, $promotion->popEvents());
    }

    public function testCreateWithValidityDates(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-12-31');

        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Seasonal Sale',
            type: PromotionType::catalogRule(),
            discount: Discount::percentage(15.0),
            validFrom: $validFrom,
            validTo: $validTo
        );

        $this->assertSame($validFrom, $promotion->validFrom());
        $this->assertSame($validTo, $promotion->validTo());
    }

    public function testCreateWithConditions(): void
    {
        $conditions = [
            'min_purchase' => 100,
            'customer_segments' => ['vip', 'premium'],
            'product_categories' => ['electronics'],
        ];

        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'VIP Sale',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(25.0),
            conditions: $conditions
        );

        $this->assertSame($conditions, $promotion->conditions());
    }

    public function testDefaultPriority(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Default Priority Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $this->assertSame(100, $promotion->priority());
    }

    public function testIsValidAtForPromotionWithoutDates(): void
    {
        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Always Valid',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $testDate = new \DateTimeImmutable();
        $this->assertTrue($promotion->isValidAt($testDate));
    }

    public function testIsValidAtForPromotionWithDates(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-12-31');

        $promotion = Promotion::create(
            id: $this->promotionId,
            tenantId: $this->tenantId,
            name: 'Seasonal Sale',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(15.0),
            validFrom: $validFrom,
            validTo: $validTo
        );

        $this->assertTrue($promotion->isValidAt(new \DateTimeImmutable('2025-06-15')));
        $this->assertFalse($promotion->isValidAt(new \DateTimeImmutable('2024-12-31')));
        $this->assertFalse($promotion->isValidAt(new \DateTimeImmutable('2026-01-01')));
    }
}
