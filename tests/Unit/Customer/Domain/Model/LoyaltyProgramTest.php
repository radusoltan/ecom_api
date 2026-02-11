<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class LoyaltyProgramTest extends TestCase
{
    private TenantId $tenantId;
    private EarningRate $earningRate;
    private Money $minOrderValue;
    private RedemptionRule $redemptionRule;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->earningRate = EarningRate::fromFloat(1.0);
        $this->minOrderValue = Money::of('50', 'USD');
        $this->redemptionRule = RedemptionRule::create(100.0, 100);
    }

    public function testCreateLoyaltyProgram(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $this->assertInstanceOf(LoyaltyProgramId::class, $program->id());
        $this->assertTrue($this->tenantId->equals($program->tenantId()));
        $this->assertSame('VIP Rewards', $program->name());
        $this->assertNull($program->description());
        $this->assertTrue($this->earningRate->equals($program->earningRate()));
        $this->assertTrue($this->minOrderValue->equals($program->minOrderValue()));
        $this->assertTrue($this->redemptionRule->equals($program->redemptionRule()));
        $this->assertNull($program->validityDays());
        $this->assertTrue($program->isActive());
        $this->assertEmpty($program->getTiers());
    }

    public function testCreateWithInvalidNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program name must be between 3 and 100 characters');

        LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'AB', // Too short
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );
    }

    public function testCreateWithTooLongNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program name must be between 3 and 100 characters');

        LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: str_repeat('A', 101), // Too long
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );
    }

    public function testUpdate(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $newEarningRate = EarningRate::fromFloat(2.0);
        $newMinOrderValue = Money::of('100', 'USD');

        $program->update(
            name: 'Premium Rewards',
            description: 'Earn more points with every purchase',
            earningRate: $newEarningRate,
            minOrderValue: $newMinOrderValue,
            validityDays: 365
        );

        $this->assertSame('Premium Rewards', $program->name());
        $this->assertSame('Earn more points with every purchase', $program->description());
        $this->assertTrue($newEarningRate->equals($program->earningRate()));
        $this->assertTrue($newMinOrderValue->equals($program->minOrderValue()));
        $this->assertSame(365, $program->validityDays());
    }

    public function testUpdateWithInvalidDescriptionThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program description cannot exceed 500 characters');

        $program->update(
            name: 'VIP Rewards',
            description: str_repeat('A', 501), // Too long
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            validityDays: null
        );
    }

    public function testUpdateWithInvalidValidityDaysThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validity days must be greater than 0 or null');

        $program->update(
            name: 'VIP Rewards',
            description: null,
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            validityDays: 0 // Invalid
        );
    }

    public function testActivate(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        // First deactivate
        $program->deactivate();
        $this->assertFalse($program->isActive());

        // Now activate
        $program->activate();
        $this->assertTrue($program->isActive());
    }

    public function testActivateAlreadyActiveThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Loyalty program is already active');

        $program->activate(); // Already active by default
    }

    public function testDeactivate(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $program->deactivate();
        $this->assertFalse($program->isActive());
    }

    public function testDeactivateAlreadyInactiveThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $program->deactivate();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Loyalty program is already inactive');

        $program->deactivate(); // Already inactive
    }

    public function testAddTier(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 5,
            freeShippingMinOrder: null,
            sortOrder: 0
        );

        $program->addTier($tier);

        $tiers = $program->getTiers();
        $this->assertCount(1, $tiers);
        $this->assertSame('Bronze', $tiers[0]->name());
    }

    public function testAddTierDuplicateNameThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $tier1 = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 5
        );

        $tier2 = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze', // Duplicate name
            threshold: 1000,
            discountPercentage: 10
        );

        $program->addTier($tier1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tier with name "Bronze" already exists');

        $program->addTier($tier2);
    }

    public function testAddTierDuplicateThresholdThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $tier1 = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: 1000,
            discountPercentage: 5
        );

        $tier2 = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Silver',
            threshold: 1000, // Duplicate threshold
            discountPercentage: 10
        );

        $program->addTier($tier1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tier with threshold 1000 already exists');

        $program->addTier($tier2);
    }

    public function testUpdateTier(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $tierId = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create(
            id: $tierId,
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 5
        );

        $program->addTier($tier);

        $program->updateTier(
            id: $tierId,
            name: 'Silver',
            threshold: 1000,
            discountPercentage: 10,
            freeShippingMinOrder: Money::of('50', 'USD'),
            sortOrder: 1
        );

        $tiers = $program->getTiers();
        $this->assertCount(1, $tiers);
        $this->assertSame('Silver', $tiers[0]->name());
        $this->assertSame(1000, $tiers[0]->threshold());
        $this->assertSame(10, $tiers[0]->discountPercentage());
    }

    public function testUpdateTierNotFoundThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $nonExistentId = LoyaltyTierId::generate();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Tier with ID ".*" not found/');

        $program->updateTier(
            id: $nonExistentId,
            name: 'Gold',
            threshold: 5000,
            discountPercentage: 15,
            freeShippingMinOrder: null,
            sortOrder: 2
        );
    }

    public function testRemoveTier(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $tierId = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create(
            id: $tierId,
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 5
        );

        $program->addTier($tier);
        $this->assertCount(1, $program->getTiers());

        $program->removeTier($tierId);
        $this->assertCount(0, $program->getTiers());
    }

    public function testRemoveTierNotFoundThrowsException(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $nonExistentId = LoyaltyTierId::generate();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Tier with ID ".*" not found/');

        $program->removeTier($nonExistentId);
    }

    public function testCalculatePointsForOrder(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(2.0), // 2 points per dollar
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: $this->redemptionRule
        );

        $orderTotal = Money::of('100', 'USD');
        $points = $program->calculatePointsForOrder($orderTotal);

        $this->assertSame(200, $points); // 100 * 2.0 = 200 points
    }

    public function testCalculatePointsForOrderBelowMinimum(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: $this->redemptionRule
        );

        $orderTotal = Money::of('30', 'USD'); // Below minimum
        $points = $program->calculatePointsForOrder($orderTotal);

        $this->assertSame(0, $points);
    }

    public function testCalculatePointsForInactiveProgram(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: $this->redemptionRule
        );

        $program->deactivate();

        $orderTotal = Money::of('100', 'USD');
        $points = $program->calculatePointsForOrder($orderTotal);

        $this->assertSame(0, $points);
    }

    public function testGetTierForPoints(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $bronze = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 0, 5);
        $silver = LoyaltyTier::create(LoyaltyTierId::generate(), 'Silver', 1000, 10);
        $gold = LoyaltyTier::create(LoyaltyTierId::generate(), 'Gold', 5000, 15);

        $program->addTier($bronze);
        $program->addTier($silver);
        $program->addTier($gold);

        // Test with 2500 points (should get Silver tier)
        $tier = $program->getTierForPoints(2500);
        $this->assertNotNull($tier);
        $this->assertSame('Silver', $tier->name());

        // Test with 5500 points (should get Gold tier)
        $tier = $program->getTierForPoints(5500);
        $this->assertNotNull($tier);
        $this->assertSame('Gold', $tier->name());

        // Test with 500 points (should get Bronze tier)
        $tier = $program->getTierForPoints(500);
        $this->assertNotNull($tier);
        $this->assertSame('Bronze', $tier->name());
    }

    public function testGetTierForPointsNoTiers(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $tier = $program->getTierForPoints(1000);
        $this->assertNull($tier);
    }

    public function testGetTiersSortedCorrectly(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule
        );

        $gold = LoyaltyTier::create(LoyaltyTierId::generate(), 'Gold', 5000, 15, null, 2);
        $bronze = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 0, 5, null, 0);
        $silver = LoyaltyTier::create(LoyaltyTierId::generate(), 'Silver', 1000, 10, null, 1);

        // Add in random order
        $program->addTier($gold);
        $program->addTier($bronze);
        $program->addTier($silver);

        $tiers = $program->getTiers();

        // Should be sorted by sortOrder ascending
        $this->assertCount(3, $tiers);
        $this->assertSame('Bronze', $tiers[0]->name());
        $this->assertSame('Silver', $tiers[1]->name());
        $this->assertSame('Gold', $tiers[2]->name());
    }

    public function testReconstituteFromPersistence(): void
    {
        $id = LoyaltyProgramId::generate();
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 15:30:00');

        $tier = LoyaltyTier::create(
            LoyaltyTierId::generate(),
            'Bronze',
            0,
            5
        );

        $program = LoyaltyProgram::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            description: 'Our premium loyalty program',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule,
            validityDays: 365,
            isActive: true,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            tiers: [$tier]
        );

        $this->assertTrue($id->equals($program->id()));
        $this->assertSame('VIP Rewards', $program->name());
        $this->assertSame('Our premium loyalty program', $program->description());
        $this->assertSame(365, $program->validityDays());
        $this->assertTrue($program->isActive());
        $this->assertEquals($createdAt, $program->createdAt());
        $this->assertEquals($updatedAt, $program->updatedAt());
        $this->assertCount(1, $program->getTiers());
    }
}
