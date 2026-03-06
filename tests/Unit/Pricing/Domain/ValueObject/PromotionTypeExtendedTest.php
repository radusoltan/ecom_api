<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\PromotionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromotionType::class)]
final class PromotionTypeExtendedTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    #[Test]
    public function itHasCorrectConstantValues(): void
    {
        self::assertSame('cart_rule', PromotionType::CART_RULE);
        self::assertSame('catalog_rule', PromotionType::CATALOG_RULE);
        self::assertSame('coupon', PromotionType::COUPON);
    }

    // -----------------------------------------------------------------------
    // Factory method round-trips
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('validTypesProvider')]
    public function itRoundTripsFromStringToString(string $value): void
    {
        $type = PromotionType::fromString($value);

        self::assertSame($value, $type->toString());
    }

    public static function validTypesProvider(): array
    {
        return [
            'cart_rule' => ['cart_rule'],
            'catalog_rule' => ['catalog_rule'],
            'coupon' => ['coupon'],
        ];
    }

    // -----------------------------------------------------------------------
    // Invalid type strings
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('invalidTypesProvider')]
    public function itThrowsForInvalidType(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PromotionType');

        PromotionType::fromString($value);
    }

    public static function invalidTypesProvider(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['CART_RULE'],
            'partial' => ['cart'],
            'unknown' => ['bundle'],
            'whitespace' => [' cart_rule'],
        ];
    }

    // -----------------------------------------------------------------------
    // Type identification methods
    // -----------------------------------------------------------------------

    #[Test]
    public function itIdentifiesCartRuleCorrectly(): void
    {
        $cartRule = PromotionType::cartRule();

        self::assertTrue($cartRule->isCartRule());
        self::assertFalse($cartRule->isCatalogRule());
        self::assertFalse($cartRule->isCoupon());
    }

    #[Test]
    public function itIdentifiesCatalogRuleCorrectly(): void
    {
        $catalogRule = PromotionType::catalogRule();

        self::assertFalse($catalogRule->isCartRule());
        self::assertTrue($catalogRule->isCatalogRule());
        self::assertFalse($catalogRule->isCoupon());
    }

    #[Test]
    public function itIdentifiesCouponCorrectly(): void
    {
        $coupon = PromotionType::coupon();

        self::assertFalse($coupon->isCartRule());
        self::assertFalse($coupon->isCatalogRule());
        self::assertTrue($coupon->isCoupon());
    }

    // -----------------------------------------------------------------------
    // equals()
    // -----------------------------------------------------------------------

    #[Test]
    public function itEqualsAnotherOfSameType(): void
    {
        $a = PromotionType::cartRule();
        $b = PromotionType::fromString('cart_rule');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentType(): void
    {
        self::assertFalse(PromotionType::cartRule()->equals(PromotionType::coupon()));
        self::assertFalse(PromotionType::catalogRule()->equals(PromotionType::cartRule()));
        self::assertFalse(PromotionType::coupon()->equals(PromotionType::catalogRule()));
    }

    #[Test]
    public function itEqualsItself(): void
    {
        $type = PromotionType::catalogRule();

        self::assertTrue($type->equals($type));
    }
}
