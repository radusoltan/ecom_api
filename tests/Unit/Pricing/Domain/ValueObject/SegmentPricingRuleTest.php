<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\SegmentPricingRule;
use PHPUnit\Framework\TestCase;

final class SegmentPricingRuleTest extends TestCase
{
    public function testItCreatesSegmentPricingRule(): void
    {
        $segment = CustomerSegment::vip();
        $discount = Discount::percentage(15.0);

        $rule = SegmentPricingRule::create($segment, $discount, 200);

        $this->assertSame($segment, $rule->segment());
        $this->assertTrue($discount->equals($rule->discount()));
        $this->assertSame(200, $rule->priority());
    }

    public function testItCreatesWithDefaultPriority(): void
    {
        $segment = CustomerSegment::wholesale();
        $discount = Discount::percentage(20.0);

        $rule = SegmentPricingRule::create($segment, $discount);

        $this->assertSame(100, $rule->priority());
    }

    public function testItThrowsExceptionForInvalidPriorityTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 1000, got -1');

        SegmentPricingRule::create(
            CustomerSegment::regular(),
            Discount::percentage(5.0),
            -1
        );
    }

    public function testItThrowsExceptionForInvalidPriorityTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 1000, got 1001');

        SegmentPricingRule::create(
            CustomerSegment::premium(),
            Discount::percentage(10.0),
            1001
        );
    }

    public function testItConvertsToAndFromArray(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(15.0),
            200
        );

        $array = $rule->toArray();

        $this->assertSame('vip', $array['segment']);
        $this->assertSame('percentage', $array['discount_type']);
        $this->assertSame(15.0, $array['discount_value']);
        $this->assertSame(200, $array['priority']);

        $reconstructed = SegmentPricingRule::fromArray($array);

        $this->assertTrue($rule->equals($reconstructed));
    }

    public function testItAppliesToMatchingSegment(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(15.0)
        );

        $this->assertTrue($rule->appliesTo(CustomerSegment::vip()));
        $this->assertFalse($rule->appliesTo(CustomerSegment::regular()));
        $this->assertFalse($rule->appliesTo(CustomerSegment::wholesale()));
    }

    public function testItChecksEquality(): void
    {
        $rule1 = SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(15.0),
            200
        );

        $rule2 = SegmentPricingRule::create(
            CustomerSegment::vip(),
            Discount::percentage(15.0),
            200
        );

        $rule3 = SegmentPricingRule::create(
            CustomerSegment::wholesale(),
            Discount::percentage(15.0),
            200
        );

        $this->assertTrue($rule1->equals($rule2));
        $this->assertFalse($rule1->equals($rule3));
    }

    public function testItWorksWithFixedAmountDiscount(): void
    {
        $rule = SegmentPricingRule::create(
            CustomerSegment::wholesale(),
            Discount::fixedAmount(5000),
            150
        );

        $array = $rule->toArray();

        $this->assertSame('wholesale', $array['segment']);
        $this->assertSame('fixed_amount', $array['discount_type']);
        $this->assertSame(50.0, $array['discount_value']);
        $this->assertSame(150, $array['priority']);
    }
}
