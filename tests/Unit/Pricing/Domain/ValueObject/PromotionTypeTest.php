<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\PromotionType;
use PHPUnit\Framework\TestCase;

final class PromotionTypeTest extends TestCase
{
    public function testCartRuleCreatesCartRuleType(): void
    {
        $type = PromotionType::cartRule();

        $this->assertTrue($type->isCartRule());
        $this->assertFalse($type->isCatalogRule());
        $this->assertFalse($type->isCoupon());
        $this->assertSame('cart_rule', $type->toString());
    }

    public function testCatalogRuleCreatesCatalogRuleType(): void
    {
        $type = PromotionType::catalogRule();

        $this->assertFalse($type->isCartRule());
        $this->assertTrue($type->isCatalogRule());
        $this->assertFalse($type->isCoupon());
        $this->assertSame('catalog_rule', $type->toString());
    }

    public function testCouponCreatesCouponType(): void
    {
        $type = PromotionType::coupon();

        $this->assertFalse($type->isCartRule());
        $this->assertFalse($type->isCatalogRule());
        $this->assertTrue($type->isCoupon());
        $this->assertSame('coupon', $type->toString());
    }

    public function testFromStringCreatesValidType(): void
    {
        $this->assertTrue(PromotionType::fromString('cart_rule')->isCartRule());
        $this->assertTrue(PromotionType::fromString('catalog_rule')->isCatalogRule());
        $this->assertTrue(PromotionType::fromString('coupon')->isCoupon());
    }

    public function testFromStringThrowsExceptionForInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PromotionType');

        PromotionType::fromString('invalid_type');
    }

    public function testEqualsReturnsTrueForSameType(): void
    {
        $type1 = PromotionType::cartRule();
        $type2 = PromotionType::cartRule();

        $this->assertTrue($type1->equals($type2));
    }

    public function testEqualsReturnsFalseForDifferentTypes(): void
    {
        $type1 = PromotionType::cartRule();
        $type2 = PromotionType::coupon();

        $this->assertFalse($type1->equals($type2));
    }
}
