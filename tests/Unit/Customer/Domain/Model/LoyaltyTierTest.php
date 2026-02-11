<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class LoyaltyTierTest extends TestCase
{
    public function testCreateTier(): void
    {
        $id = LoyaltyTierId::generate();
        $freeShippingMinOrder = Money::of('50', 'USD');

        $tier = LoyaltyTier::create(
            id: $id,
            name: 'Gold',
            threshold: 5000,
            discountPercentage: 15,
            freeShippingMinOrder: $freeShippingMinOrder,
            sortOrder: 2
        );

        $this->assertTrue($id->equals($tier->id()));
        $this->assertSame('Gold', $tier->name());
        $this->assertSame(5000, $tier->threshold());
        $this->assertSame(15, $tier->discountPercentage());
        $this->assertNotNull($tier->freeShippingMinOrder());
        $this->assertTrue($freeShippingMinOrder->equals($tier->freeShippingMinOrder()));
        $this->assertSame(2, $tier->sortOrder());
        $this->assertTrue($tier->hasFreeShippingBenefit());
    }

    public function testCreateWithInvalidNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tier name must be between 3 and 50 characters');

        LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'AB', // Too short
            threshold: 0,
            discountPercentage: 5
        );
    }

    public function testCreateWithTooLongNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tier name must be between 3 and 50 characters');

        LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: str_repeat('A', 51), // Too long
            threshold: 0,
            discountPercentage: 5
        );
    }

    public function testCreateWithNegativeThresholdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tier threshold must be greater than or equal to 0');

        LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: -1, // Negative
            discountPercentage: 5
        );
    }

    public function testCreateWithInvalidDiscountPercentageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

        LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 101 // Too high
        );
    }

    public function testCreateWithNegativeDiscountPercentageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

        LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: 0,
            discountPercentage: -1 // Negative
        );
    }

    public function testCreateWithInvalidSortOrderThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sort order must be greater than or equal to 0');

        LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 5,
            freeShippingMinOrder: null,
            sortOrder: -1 // Negative
        );
    }

    public function testGetters(): void
    {
        $id = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create(
            id: $id,
            name: 'Silver',
            threshold: 1000,
            discountPercentage: 10,
            freeShippingMinOrder: null,
            sortOrder: 1
        );

        $this->assertTrue($id->equals($tier->id()));
        $this->assertSame('Silver', $tier->name());
        $this->assertSame(1000, $tier->threshold());
        $this->assertSame(10, $tier->discountPercentage());
        $this->assertNull($tier->freeShippingMinOrder());
        $this->assertSame(1, $tier->sortOrder());
        $this->assertFalse($tier->hasFreeShippingBenefit());
    }

    public function testEquality(): void
    {
        $id1 = LoyaltyTierId::generate();
        $id2 = LoyaltyTierId::generate();

        $tier1 = LoyaltyTier::create($id1, 'Bronze', 0, 5);
        $tier2 = LoyaltyTier::create($id1, 'Bronze', 0, 5);
        $tier3 = LoyaltyTier::create($id2, 'Silver', 1000, 10);

        $this->assertTrue($tier1->equals($tier2));
        $this->assertFalse($tier1->equals($tier3));
    }

    public function testToArray(): void
    {
        $id = LoyaltyTierId::generate();
        $freeShippingMinOrder = Money::of('50', 'USD');

        $tier = LoyaltyTier::create(
            id: $id,
            name: 'Platinum',
            threshold: 10000,
            discountPercentage: 20,
            freeShippingMinOrder: $freeShippingMinOrder,
            sortOrder: 3
        );

        $array = $tier->toArray();

        $this->assertIsArray($array);
        $this->assertSame($id->toString(), $array['id']);
        $this->assertSame('Platinum', $array['name']);
        $this->assertSame(10000, $array['threshold']);
        $this->assertSame(20, $array['discountPercentage']);
        $this->assertIsArray($array['freeShippingMinOrder']);
        $this->assertSame(3, $array['sortOrder']);
    }
}
