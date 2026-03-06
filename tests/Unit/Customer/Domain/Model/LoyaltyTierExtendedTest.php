<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoyaltyTier::class)]
final class LoyaltyTierExtendedTest extends TestCase
{
    // -----------------------------------------------------------------------
    // toArray — null freeShippingMinOrder
    // -----------------------------------------------------------------------

    #[Test]
    public function itToArrayWithNullFreeShippingMinOrder(): void
    {
        $id = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create(
            id: $id,
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 5,
            freeShippingMinOrder: null,
            sortOrder: 0,
        );

        $array = $tier->toArray();

        self::assertIsArray($array);
        self::assertSame($id->toString(), $array['id']);
        self::assertSame('Bronze', $array['name']);
        self::assertSame(0, $array['threshold']);
        self::assertSame(5, $array['discountPercentage']);
        self::assertNull($array['freeShippingMinOrder']);
        self::assertSame(0, $array['sortOrder']);
    }

    #[Test]
    public function itToArrayWithFreeShippingMinOrder(): void
    {
        $id = LoyaltyTierId::generate();
        $minOrder = Money::of('49.99', 'USD');

        $tier = LoyaltyTier::create(
            id: $id,
            name: 'Platinum',
            threshold: 10000,
            discountPercentage: 20,
            freeShippingMinOrder: $minOrder,
            sortOrder: 3,
        );

        $array = $tier->toArray();

        self::assertIsArray($array['freeShippingMinOrder']);
    }

    // -----------------------------------------------------------------------
    // Boundary values
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsZeroThreshold(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Entry',
            threshold: 0,
            discountPercentage: 0,
        );

        self::assertSame(0, $tier->threshold());
        self::assertSame(0, $tier->discountPercentage());
    }

    #[Test]
    public function itAcceptsMaxDiscountPercentageOf100(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Free',
            threshold: 99999,
            discountPercentage: 100,
        );

        self::assertSame(100, $tier->discountPercentage());
    }

    #[Test]
    public function itAcceptsZeroSortOrder(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Basic',
            threshold: 0,
            discountPercentage: 5,
            sortOrder: 0,
        );

        self::assertSame(0, $tier->sortOrder());
    }

    #[Test]
    public function itAcceptsLargeSortOrder(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'VVV',
            threshold: 0,
            discountPercentage: 5,
            sortOrder: 9999,
        );

        self::assertSame(9999, $tier->sortOrder());
    }

    #[Test]
    public function itAcceptsNameOfExactlyThreeCharacters(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Pro',
            threshold: 0,
            discountPercentage: 5,
        );

        self::assertSame('Pro', $tier->name());
    }

    #[Test]
    public function itAcceptsNameOfExactlyFiftyCharacters(): void
    {
        $name = str_repeat('A', 50);

        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: $name,
            threshold: 0,
            discountPercentage: 5,
        );

        self::assertSame($name, $tier->name());
    }

    #[Test]
    public function itTrimsNameWhitespace(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: '  Bronze  ',
            threshold: 0,
            discountPercentage: 5,
        );

        self::assertSame('Bronze', $tier->name());
    }

    // -----------------------------------------------------------------------
    // hasFreeShippingBenefit
    // -----------------------------------------------------------------------

    #[Test]
    public function itHasFreeShippingBenefitWhenMinOrderSet(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'VIP Plus',
            threshold: 5000,
            discountPercentage: 10,
            freeShippingMinOrder: Money::of('0', 'USD'),
        );

        self::assertTrue($tier->hasFreeShippingBenefit());
        self::assertNotNull($tier->freeShippingMinOrder());
    }

    #[Test]
    public function itDoesNotHaveFreeShippingBenefitWhenMinOrderNull(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Basic Plus',
            threshold: 1000,
            discountPercentage: 5,
            freeShippingMinOrder: null,
        );

        self::assertFalse($tier->hasFreeShippingBenefit());
        self::assertNull($tier->freeShippingMinOrder());
    }

    // -----------------------------------------------------------------------
    // equals
    // -----------------------------------------------------------------------

    #[Test]
    public function itTiersWithSameIdAreEqual(): void
    {
        $id = LoyaltyTierId::generate();
        $tier1 = LoyaltyTier::create($id, 'Bronze', 0, 5);
        $tier2 = LoyaltyTier::create($id, 'Different Name', 9999, 100);

        self::assertTrue($tier1->equals($tier2));
    }

    #[Test]
    public function itTiersWithDifferentIdsAreNotEqual(): void
    {
        $tier1 = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 0, 5);
        $tier2 = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 0, 5);

        self::assertFalse($tier1->equals($tier2));
    }

    // -----------------------------------------------------------------------
    // Validation edge cases already covered but ensuring all paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenNameHasTwoCharsAfterTrim(): void
    {
        // After trim, name is 2 chars (below 3 minimum)
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Tier name must be between 3 and 50 characters');

        LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: ' AB ',
            threshold: 0,
            discountPercentage: 5,
        );
    }
}
