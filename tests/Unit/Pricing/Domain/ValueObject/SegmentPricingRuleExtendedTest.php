<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\SegmentPricingRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SegmentPricingRule::class)]
final class SegmentPricingRuleExtendedTest extends TestCase
{
    // -----------------------------------------------------------------------
    // create()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesWithDefaultPriority(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(15.0),
        );

        self::assertSame(100, $rule->priority());
        self::assertTrue($rule->segment()->isVip());
        self::assertSame(15.0, $rule->discount()->value());
    }

    #[Test]
    public function itCreatesWithCustomPriority(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::wholesale(),
            Discount::percentage(25.0),
            500,
        );

        self::assertSame(500, $rule->priority());
        self::assertTrue($rule->segment()->isWholesale());
    }

    #[Test]
    public function itCreatesWithFixedAmountDiscount(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::premium(),
            Discount::fixedAmount(1000),
        );

        self::assertTrue($rule->discount()->type()->isFixedAmount());
        self::assertSame(10.0, $rule->discount()->value());
    }

    #[Test]
    public function itAcceptsPriorityZero(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::regular(),
            Discount::percentage(5.0),
            0,
        );

        self::assertSame(0, $rule->priority());
    }

    #[Test]
    public function itAcceptsPriorityThousand(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(20.0),
            1000,
        );

        self::assertSame(1000, $rule->priority());
    }

    #[Test]
    public function itThrowsWhenPriorityIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 1000');

        SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(10.0),
            -1,
        );
    }

    #[Test]
    public function itThrowsWhenPriorityExceedsThousand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 1000');

        SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(10.0),
            1001,
        );
    }

    // -----------------------------------------------------------------------
    // fromArray()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesFromArrayWithPercentage(): void
    {
        $rule = SegmentPricingRule::fromArray([
            'segment' => 'vip',
            'discount_type' => 'percentage',
            'discount_value' => 20.0,
            'priority' => 200,
        ]);

        self::assertTrue($rule->segment()->isVip());
        self::assertTrue($rule->discount()->type()->isPercentage());
        self::assertSame(20.0, $rule->discount()->value());
        self::assertSame(200, $rule->priority());
    }

    #[Test]
    public function itCreatesFromArrayWithFixedAmount(): void
    {
        $rule = SegmentPricingRule::fromArray([
            'segment' => 'wholesale',
            'discount_type' => 'fixed_amount',
            'discount_value' => 50.0,
        ]);

        self::assertTrue($rule->segment()->isWholesale());
        self::assertTrue($rule->discount()->type()->isFixedAmount());
        self::assertSame(50.0, $rule->discount()->value());
        // Default priority when not provided
        self::assertSame(100, $rule->priority());
    }

    #[Test]
    public function itCreatesFromArrayUsingDefaultPriority(): void
    {
        $rule = SegmentPricingRule::fromArray([
            'segment' => 'regular',
            'discount_type' => 'percentage',
            'discount_value' => 5.0,
        ]);

        self::assertSame(100, $rule->priority());
    }

    // -----------------------------------------------------------------------
    // toArray()
    // -----------------------------------------------------------------------

    #[Test]
    public function itConvertsToArrayCorrectly(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(15.0),
            300,
        );

        $array = $rule->toArray();

        self::assertSame('vip', $array['segment']);
        self::assertSame('percentage', $array['discount_type']);
        self::assertSame(15.0, $array['discount_value']);
        self::assertSame(300, $array['priority']);
    }

    #[Test]
    public function itConvertsToArrayWithFixedAmountDiscount(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::wholesale(),
            Discount::fixedAmount(2500),
            150,
        );

        $array = $rule->toArray();

        self::assertSame('wholesale', $array['segment']);
        self::assertSame('fixed_amount', $array['discount_type']);
        self::assertSame(25.0, $array['discount_value']);
        self::assertSame(150, $array['priority']);
    }

    #[Test]
    public function itRoundTripsViaArray(): void
    {
        $original = SegmentPricingRule::create(
            CustomerSegment::premium(),
            Discount::percentage(12.5),
            400,
        );

        $reconstructed = SegmentPricingRule::fromArray($original->toArray());

        self::assertTrue($original->equals($reconstructed));
    }

    // -----------------------------------------------------------------------
    // appliesTo()
    // -----------------------------------------------------------------------

    #[Test]
    public function itAppliesToMatchingSegment(): void
    {
        $rule = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0));

        self::assertTrue($rule->appliesTo(CustomerSegment::vip()));
    }

    #[Test]
    public function itDoesNotApplyToNonMatchingSegment(): void
    {
        $rule = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0));

        self::assertFalse($rule->appliesTo(CustomerSegment::regular()));
        self::assertFalse($rule->appliesTo(CustomerSegment::wholesale()));
        self::assertFalse($rule->appliesTo(CustomerSegment::premium()));
    }

    #[Test]
    public function itAppliesToAllSegmentTypes(): void
    {
        $segments = [
            CustomerSegment::regular(),
            CustomerSegment::vip(),
            CustomerSegment::wholesale(),
            CustomerSegment::premium(),
        ];

        foreach ($segments as $segment) {
            $rule = SegmentPricingRule::create($segment, Discount::percentage(10.0));
            self::assertTrue($rule->appliesTo($segment));
        }
    }

    // -----------------------------------------------------------------------
    // equals()
    // -----------------------------------------------------------------------

    #[Test]
    public function itEqualsIdenticalRule(): void
    {
        $rule1 = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0), 200);
        $rule2 = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0), 200);

        self::assertTrue($rule1->equals($rule2));
    }

    #[Test]
    public function itDoesNotEqualRuleWithDifferentSegment(): void
    {
        $rule1 = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0));
        $rule2 = SegmentPricingRule::create(CustomerSegment::regular(), Discount::percentage(15.0));

        self::assertFalse($rule1->equals($rule2));
    }

    #[Test]
    public function itDoesNotEqualRuleWithDifferentDiscount(): void
    {
        $rule1 = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0));
        $rule2 = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(20.0));

        self::assertFalse($rule1->equals($rule2));
    }

    #[Test]
    public function itDoesNotEqualRuleWithDifferentPriority(): void
    {
        $rule1 = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0), 100);
        $rule2 = SegmentPricingRule::create(CustomerSegment::vip(), Discount::percentage(15.0), 200);

        self::assertFalse($rule1->equals($rule2));
    }

    // -----------------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesAllGetters(): void
    {
        $segment = CustomerSegment::vip();
        $discount = Discount::percentage(18.0);
        $rule = SegmentPricingRule::create($segment, $discount, 350);

        self::assertTrue($rule->segment()->equals($segment));
        self::assertTrue($rule->discount()->equals($discount));
        self::assertSame(350, $rule->priority());
    }
}
