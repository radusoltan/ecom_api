<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Promotion::class)]
final class PromotionExtendedTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // requiresCoupon
    // -----------------------------------------------------------------------

    #[Test]
    public function itRequiresCouponForCouponType(): void
    {
        $promotion = $this->makePromotion(
            type: PromotionType::coupon(),
            couponCode: CouponCode::fromString('SAVE20'),
        );

        self::assertTrue($promotion->requiresCoupon());
    }

    #[Test]
    public function itDoesNotRequireCouponForCartRule(): void
    {
        $promotion = $this->makePromotion(type: PromotionType::cartRule());

        self::assertFalse($promotion->requiresCoupon());
    }

    #[Test]
    public function itDoesNotRequireCouponForCatalogRule(): void
    {
        $promotion = $this->makePromotion(type: PromotionType::catalogRule());

        self::assertFalse($promotion->requiresCoupon());
    }

    // -----------------------------------------------------------------------
    // Validation: coupon code presence
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenNonCouponTypeReceivesCouponCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon code should not be provided for non-coupon promotions');

        Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Cart Rule With Coupon',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            couponCode: CouponCode::fromString('SAVE10'),
        );
    }

    #[Test]
    public function itThrowsWhenCatalogRuleTypeReceivesCouponCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon code should not be provided for non-coupon promotions');

        Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Catalog Rule With Coupon',
            type: PromotionType::catalogRule(),
            discount: Discount::percentage(10.0),
            couponCode: CouponCode::fromString('SAVE10'),
        );
    }

    // -----------------------------------------------------------------------
    // Name boundary validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsNameOfExactlyThreeCharacters(): void
    {
        $promotion = $this->makePromotion(name: 'ABC');

        self::assertSame('ABC', $promotion->name());
    }

    #[Test]
    public function itAcceptsNameOfExactlyHundredCharacters(): void
    {
        $name = str_repeat('A', 100);
        $promotion = $this->makePromotion(name: $name);

        self::assertSame($name, $promotion->name());
    }

    #[Test]
    public function itThrowsWhenNameIsTwoCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion name must be between 3 and 100 characters');

        $this->makePromotion(name: 'AB');
    }

    #[Test]
    public function itThrowsWhenNameIsOneHundredAndOneCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion name must be between 3 and 100 characters');

        $this->makePromotion(name: str_repeat('X', 101));
    }

    // -----------------------------------------------------------------------
    // Priority boundary validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsPriorityOfOne(): void
    {
        $promotion = $this->makePromotion(priority: 1);

        self::assertSame(1, $promotion->priority());
    }

    #[Test]
    public function itAcceptsPriorityOfThousand(): void
    {
        $promotion = $this->makePromotion(priority: 1000);

        self::assertSame(1000, $promotion->priority());
    }

    #[Test]
    public function itThrowsWhenPriorityIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion priority must be between 1 and 1000');

        $this->makePromotion(priority: 0);
    }

    // -----------------------------------------------------------------------
    // Date range validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenValidFromEqualsValidTo(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom must be before validTo');

        Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Equal Dates',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            validFrom: $date,
            validTo: $date,
        );
    }

    #[Test]
    public function itThrowsOnUpdateWithInvalidDateRange(): void
    {
        $promotion = $this->makePromotion();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom must be before validTo');

        $promotion->update(
            name: 'Updated',
            discount: Discount::percentage(10.0),
            priority: 100,
            conditions: [],
            validFrom: new \DateTimeImmutable('2025-12-31'),
            validTo: new \DateTimeImmutable('2025-01-01'),
        );
    }

    #[Test]
    public function itThrowsOnUpdateWithShortName(): void
    {
        $promotion = $this->makePromotion();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion name must be between 3 and 100 characters');

        $promotion->update(
            name: 'AB',
            discount: Discount::percentage(10.0),
            priority: 100,
            conditions: [],
            validFrom: null,
            validTo: null,
        );
    }

    #[Test]
    public function itThrowsOnUpdateWithInvalidPriority(): void
    {
        $promotion = $this->makePromotion();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion priority must be between 1 and 1000');

        $promotion->update(
            name: 'Valid Name',
            discount: Discount::percentage(10.0),
            priority: 0,
            conditions: [],
            validFrom: null,
            validTo: null,
        );
    }

    // -----------------------------------------------------------------------
    // isValidAt boundary cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsValidAtExactValidFromBoundary(): void
    {
        $validFrom = new \DateTimeImmutable('2025-06-01 00:00:00');
        $validTo = new \DateTimeImmutable('2025-06-30 23:59:59');

        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Boundary Test',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            validFrom: $validFrom,
            validTo: $validTo,
        );

        // At exactly validFrom — should be valid (not before)
        self::assertTrue($promotion->isValidAt($validFrom));
    }

    #[Test]
    public function itIsNotValidBeforeValidFrom(): void
    {
        $validFrom = new \DateTimeImmutable('2025-06-01');
        $validTo = new \DateTimeImmutable('2025-06-30');

        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Future Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            validFrom: $validFrom,
            validTo: $validTo,
        );

        self::assertFalse($promotion->isValidAt(new \DateTimeImmutable('2025-05-31')));
    }

    #[Test]
    public function itIsNotValidAfterValidTo(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-01-31');

        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Past Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            validFrom: $validFrom,
            validTo: $validTo,
        );

        self::assertFalse($promotion->isValidAt(new \DateTimeImmutable('2025-02-01')));
    }

    // -----------------------------------------------------------------------
    // isApplicableToSegment
    // -----------------------------------------------------------------------

    #[Test]
    public function itAppliesToAllSegmentsWhenNoTargetSegmentsDefined(): void
    {
        $promotion = $this->makePromotion();

        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::regular()));
        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::vip()));
        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::wholesale()));
        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::premium()));
    }

    #[Test]
    public function itAppliesToTargetedSegmentOnly(): void
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'VIP Only Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            targetSegments: [CustomerSegment::vip()],
        );

        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::vip()));
        self::assertFalse($promotion->isApplicableToSegment(CustomerSegment::regular()));
        self::assertFalse($promotion->isApplicableToSegment(CustomerSegment::wholesale()));
    }

    #[Test]
    public function itAppliesToMultipleTargetSegments(): void
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'VIP and Wholesale',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(15.0),
            targetSegments: [CustomerSegment::vip(), CustomerSegment::wholesale()],
        );

        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::vip()));
        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::wholesale()));
        self::assertFalse($promotion->isApplicableToSegment(CustomerSegment::regular()));
        self::assertFalse($promotion->isApplicableToSegment(CustomerSegment::premium()));
    }

    // -----------------------------------------------------------------------
    // addTargetSegment
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsTargetSegment(): void
    {
        $promotion = $this->makePromotion();

        $promotion->addTargetSegment(CustomerSegment::vip());

        self::assertCount(1, $promotion->targetSegments());
        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::vip()));
    }

    #[Test]
    public function itAddsMultipleTargetSegments(): void
    {
        $promotion = $this->makePromotion();

        $promotion->addTargetSegment(CustomerSegment::vip());
        $promotion->addTargetSegment(CustomerSegment::premium());

        self::assertCount(2, $promotion->targetSegments());
    }

    #[Test]
    public function itThrowsWhenAddingDuplicateTargetSegment(): void
    {
        $promotion = $this->makePromotion();
        $promotion->addTargetSegment(CustomerSegment::vip());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Segment vip is already a target for this promotion');

        $promotion->addTargetSegment(CustomerSegment::vip());
    }

    #[Test]
    public function itUpdatesTimestampWhenAddingTargetSegment(): void
    {
        $promotion = $this->makePromotion();
        $before = $promotion->updatedAt();

        usleep(1000);
        $promotion->addTargetSegment(CustomerSegment::vip());

        self::assertGreaterThanOrEqual($before, $promotion->updatedAt());
    }

    // -----------------------------------------------------------------------
    // removeTargetSegment
    // -----------------------------------------------------------------------

    #[Test]
    public function itRemovesTargetSegment(): void
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Multi Segment',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            targetSegments: [CustomerSegment::vip(), CustomerSegment::wholesale()],
        );

        $promotion->removeTargetSegment(CustomerSegment::vip());

        self::assertCount(1, $promotion->targetSegments());
        self::assertFalse($promotion->isApplicableToSegment(CustomerSegment::vip()));
        self::assertTrue($promotion->isApplicableToSegment(CustomerSegment::wholesale()));
    }

    #[Test]
    public function itThrowsWhenRemovingNonExistentTargetSegment(): void
    {
        $promotion = $this->makePromotion();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Segment vip is not a target for this promotion');

        $promotion->removeTargetSegment(CustomerSegment::vip());
    }

    #[Test]
    public function itReindexesTargetSegmentsAfterRemoval(): void
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Three Segments',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            targetSegments: [
                CustomerSegment::regular(),
                CustomerSegment::vip(),
                CustomerSegment::wholesale(),
            ],
        );

        $promotion->removeTargetSegment(CustomerSegment::vip());

        $segments = $promotion->targetSegments();
        self::assertCount(2, $segments);
        self::assertArrayHasKey(0, $segments);
        self::assertArrayHasKey(1, $segments);
        self::assertArrayNotHasKey(2, $segments);
    }

    #[Test]
    public function itUpdatesTimestampWhenRemovingTargetSegment(): void
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'With Segment',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            targetSegments: [CustomerSegment::vip()],
        );
        $before = $promotion->updatedAt();

        usleep(1000);
        $promotion->removeTargetSegment(CustomerSegment::vip());

        self::assertGreaterThanOrEqual($before, $promotion->updatedAt());
    }

    // -----------------------------------------------------------------------
    // Getters coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesAllGetters(): void
    {
        $id = PromotionId::generate();
        $coupon = CouponCode::fromString('MYCODE99');
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-12-31');

        $promotion = Promotion::create(
            id: $id,
            tenantId: $this->tenantId,
            name: 'Full Getters',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(10.0),
            priority: 250,
            couponCode: $coupon,
            conditions: ['min_cart' => 50],
            targetSegments: [CustomerSegment::vip()],
            validFrom: $validFrom,
            validTo: $validTo,
        );

        self::assertTrue($promotion->id()->equals($id));
        self::assertTrue($promotion->tenantId()->equals($this->tenantId));
        self::assertSame('Full Getters', $promotion->name());
        self::assertTrue($promotion->type()->isCoupon());
        self::assertTrue($promotion->discount()->type()->isFixedAmount());
        self::assertSame(250, $promotion->priority());
        self::assertFalse($promotion->isActive());
        self::assertNotNull($promotion->couponCode());
        self::assertSame(['min_cart' => 50], $promotion->conditions());
        self::assertCount(1, $promotion->targetSegments());
        self::assertEquals($validFrom, $promotion->validFrom());
        self::assertEquals($validTo, $promotion->validTo());
        self::assertInstanceOf(\DateTimeImmutable::class, $promotion->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $promotion->updatedAt());
    }

    #[Test]
    public function itExposesCouponCodeAsNullForNonCoupon(): void
    {
        $promotion = $this->makePromotion(type: PromotionType::cartRule());

        self::assertNull($promotion->couponCode());
    }

    // -----------------------------------------------------------------------
    // isApplicable combined check
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsNotApplicableWhenActiveButBeforeValidFrom(): void
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Future Active',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            validFrom: new \DateTimeImmutable('+1 day'),
            validTo: new \DateTimeImmutable('+7 days'),
        );
        $promotion->activate();

        self::assertFalse($promotion->isApplicable(new \DateTimeImmutable()));
    }

    #[Test]
    public function itIsNotApplicableWhenInactiveAndWithinValidRange(): void
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Inactive In Range',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            validFrom: new \DateTimeImmutable('-1 day'),
            validTo: new \DateTimeImmutable('+1 day'),
        );

        // Not activated
        self::assertFalse($promotion->isApplicable(new \DateTimeImmutable()));
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstitutesFromPersistenceWithTargetSegments(): void
    {
        $id = PromotionId::generate();
        $createdAt = new \DateTimeImmutable('2025-01-01');
        $updatedAt = new \DateTimeImmutable('2025-06-01');

        $promotion = Promotion::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            name: 'Reconstituted',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            priority: 300,
            isActive: true,
            couponCode: null,
            conditions: ['key' => 'value'],
            targetSegments: [CustomerSegment::vip(), CustomerSegment::premium()],
            validFrom: null,
            validTo: null,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertTrue($promotion->id()->equals($id));
        self::assertTrue($promotion->isActive());
        self::assertSame(300, $promotion->priority());
        self::assertCount(2, $promotion->targetSegments());
        self::assertSame($createdAt, $promotion->createdAt());
        self::assertSame($updatedAt, $promotion->updatedAt());
        self::assertEmpty($promotion->popEvents());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makePromotion(
        string $name = 'Test Promotion',
        ?PromotionType $type = null,
        int $priority = 100,
        ?CouponCode $couponCode = null,
    ): Promotion {
        $type = $type ?? PromotionType::cartRule();

        return Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            type: $type,
            discount: Discount::percentage(10.0),
            priority: $priority,
            couponCode: $couponCode,
        );
    }
}
