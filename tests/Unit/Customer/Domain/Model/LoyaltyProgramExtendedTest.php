<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Event\LoyaltyProgramActivated;
use App\Customer\Domain\Event\LoyaltyProgramCreated;
use App\Customer\Domain\Event\LoyaltyProgramDeactivated;
use App\Customer\Domain\Event\LoyaltyProgramUpdated;
use App\Customer\Domain\Event\LoyaltyTierAdded;
use App\Customer\Domain\Event\LoyaltyTierRemoved;
use App\Customer\Domain\Event\LoyaltyTierUpdated;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoyaltyProgram::class)]
final class LoyaltyProgramExtendedTest extends TestCase
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

    private function createProgram(string $name = 'VIP Rewards'): LoyaltyProgram
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: $name,
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule,
        );
        // Clear creation event
        $program->popEvents();

        return $program;
    }

    // -----------------------------------------------------------------------
    // Creation events
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsCreatedEventOnCreate(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'Test Program',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule,
        );

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramCreated::class, $events[0]);
    }

    #[Test]
    public function itReconstituteDoesNotRecordEvents(): void
    {
        $program = LoyaltyProgram::reconstituteFromPersistence(
            id: LoyaltyProgramId::generate(),
            tenantId: $this->tenantId,
            name: 'VIP Rewards',
            description: null,
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule,
            validityDays: null,
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        self::assertFalse($program->hasEvents());
    }

    // -----------------------------------------------------------------------
    // setRedemptionRule
    // -----------------------------------------------------------------------

    #[Test]
    public function itSetsNewRedemptionRuleAndRecordsEvent(): void
    {
        $program = $this->createProgram();
        $newRule = RedemptionRule::create(50.0, 50);

        $program->setRedemptionRule($newRule);

        self::assertTrue($newRule->equals($program->redemptionRule()));

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramUpdated::class, $events[0]);
    }

    #[Test]
    public function itDoesNotRecordEventWhenRedemptionRuleUnchanged(): void
    {
        $program = $this->createProgram();
        $sameRule = RedemptionRule::create(100.0, 100); // Same as setup

        $program->setRedemptionRule($sameRule);

        self::assertFalse($program->hasEvents());
    }

    // -----------------------------------------------------------------------
    // update — no-change path
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNotRecordEventWhenUpdateHasNoChanges(): void
    {
        $program = $this->createProgram('VIP Rewards');

        // Update with identical values
        $program->update(
            name: 'VIP Rewards',
            description: null,
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            validityDays: null,
        );

        self::assertFalse($program->hasEvents());
    }

    #[Test]
    public function itRecordsEventWhenNameChanges(): void
    {
        $program = $this->createProgram('Original Name');

        $program->update(
            name: 'New Name',
            description: null,
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            validityDays: null,
        );

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramUpdated::class, $events[0]);
    }

    #[Test]
    public function itRecordsEventWhenDescriptionChanges(): void
    {
        $program = $this->createProgram();

        $program->update(
            name: 'VIP Rewards',
            description: 'A brand new description',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            validityDays: null,
        );

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramUpdated::class, $events[0]);
        self::assertSame('A brand new description', $program->description());
    }

    #[Test]
    public function itRecordsEventWhenEarningRateChanges(): void
    {
        $program = $this->createProgram();

        $program->update(
            name: 'VIP Rewards',
            description: null,
            earningRate: EarningRate::fromFloat(2.0),
            minOrderValue: $this->minOrderValue,
            validityDays: null,
        );

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramUpdated::class, $events[0]);
    }

    #[Test]
    public function itRecordsEventWhenValidityDaysChanges(): void
    {
        $program = $this->createProgram();

        $program->update(
            name: 'VIP Rewards',
            description: null,
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            validityDays: 180,
        );

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertSame(180, $program->validityDays());
    }

    #[Test]
    public function itThrowsWhenValidityDaysIsNegative(): void
    {
        $program = $this->createProgram();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Validity days must be greater than 0 or null');

        $program->update(
            name: 'VIP Rewards',
            description: null,
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            validityDays: -1,
        );
    }

    // -----------------------------------------------------------------------
    // activate / deactivate events
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsActivatedEventOnActivate(): void
    {
        $program = $this->createProgram();
        $program->deactivate();
        $program->popEvents(); // Clear deactivate event

        $program->activate();

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramActivated::class, $events[0]);
    }

    #[Test]
    public function itRecordsDeactivatedEventOnDeactivate(): void
    {
        $program = $this->createProgram();

        $program->deactivate();

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramDeactivated::class, $events[0]);
    }

    // -----------------------------------------------------------------------
    // addTier events
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsTierAddedEventOnAddTier(): void
    {
        $program = $this->createProgram();
        $tier = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 0, 5);

        $program->addTier($tier);

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyTierAdded::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenAddingTierWithDuplicateId(): void
    {
        $program = $this->createProgram();
        $tierId = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create($tierId, 'Bronze', 0, 5);

        $program->addTier($tier);

        // Another tier with the same ID
        $duplicateTier = LoyaltyTier::create($tierId, 'Silver', 1000, 10);

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Tier with ID "'.$tierId->toString().'" already exists');

        $program->addTier($duplicateTier);
    }

    #[Test]
    public function itAddsTierWithCaseInsensitiveDuplicateNameCheck(): void
    {
        $program = $this->createProgram();
        $tier1 = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 0, 5);
        $tier2 = LoyaltyTier::create(LoyaltyTierId::generate(), 'BRONZE', 1000, 10);

        $program->addTier($tier1);

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Tier with name "BRONZE" already exists');

        $program->addTier($tier2);
    }

    // -----------------------------------------------------------------------
    // updateTier events and duplicate checks
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsTierUpdatedEventOnUpdateTier(): void
    {
        $program = $this->createProgram();
        $tierId = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create($tierId, 'Bronze', 0, 5);
        $program->addTier($tier);
        $program->popEvents();

        $program->updateTier(
            id: $tierId,
            name: 'Silver',
            threshold: 1000,
            discountPercentage: 10,
            freeShippingMinOrder: null,
            sortOrder: 1,
        );

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyTierUpdated::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenUpdateTierHasDuplicateName(): void
    {
        $program = $this->createProgram();
        $tierId1 = LoyaltyTierId::generate();
        $tierId2 = LoyaltyTierId::generate();

        $tier1 = LoyaltyTier::create($tierId1, 'Bronze', 0, 5);
        $tier2 = LoyaltyTier::create($tierId2, 'Silver', 1000, 10);
        $program->addTier($tier1);
        $program->addTier($tier2);

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Tier with name "Silver" already exists');

        // Try to rename tier1 to 'Silver' — same as tier2
        $program->updateTier(
            id: $tierId1,
            name: 'Silver',
            threshold: 500,
            discountPercentage: 7,
            freeShippingMinOrder: null,
            sortOrder: 0,
        );
    }

    #[Test]
    public function itThrowsWhenUpdateTierHasDuplicateThreshold(): void
    {
        $program = $this->createProgram();
        $tierId1 = LoyaltyTierId::generate();
        $tierId2 = LoyaltyTierId::generate();

        $tier1 = LoyaltyTier::create($tierId1, 'Bronze', 0, 5);
        $tier2 = LoyaltyTier::create($tierId2, 'Silver', 1000, 10);
        $program->addTier($tier1);
        $program->addTier($tier2);

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Tier with threshold 1000 already exists');

        // Try to set tier1's threshold to 1000 — same as tier2
        $program->updateTier(
            id: $tierId1,
            name: 'NewName',
            threshold: 1000,
            discountPercentage: 7,
            freeShippingMinOrder: null,
            sortOrder: 0,
        );
    }

    #[Test]
    public function itAllowsUpdatingTierWithSameNameForSameTier(): void
    {
        $program = $this->createProgram();
        $tierId = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create($tierId, 'Bronze', 0, 5);
        $program->addTier($tier);

        // Should not throw: updating a tier can keep the same name
        $program->updateTier(
            id: $tierId,
            name: 'Bronze', // Same name, same tier
            threshold: 100, // Different threshold
            discountPercentage: 7,
            freeShippingMinOrder: null,
            sortOrder: 0,
        );

        $tiers = $program->getTiers();
        self::assertCount(1, $tiers);
        self::assertSame('Bronze', $tiers[0]->name());
        self::assertSame(100, $tiers[0]->threshold());
    }

    // -----------------------------------------------------------------------
    // removeTier events
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsTierRemovedEventOnRemoveTier(): void
    {
        $program = $this->createProgram();
        $tierId = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create($tierId, 'Bronze', 0, 5);
        $program->addTier($tier);
        $program->popEvents();

        $program->removeTier($tierId);

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyTierRemoved::class, $events[0]);
    }

    // -----------------------------------------------------------------------
    // calculateDiscountForPoints
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesDiscountForPoints(): void
    {
        // Rule: 100 points = $1 discount (conversionRate=100)
        $program = $this->createProgram();

        $discount = $program->calculateDiscountForPoints(500);

        // 500 / 100 = $5
        self::assertSame(5.0, $discount->amount());
    }

    #[Test]
    public function itCalculateDiscountThrowsWhenBelowMinimum(): void
    {
        // Rule: min 100 points to redeem
        $program = $this->createProgram();

        self::expectException(\InvalidArgumentException::class);

        $program->calculateDiscountForPoints(50); // Below min of 100
    }

    // -----------------------------------------------------------------------
    // getTierForPoints — edge: points below all thresholds
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenPointsBelowAllTierThresholds(): void
    {
        $program = $this->createProgram();
        $program->addTier(LoyaltyTier::create(LoyaltyTierId::generate(), 'Silver', 1000, 10));
        $program->addTier(LoyaltyTier::create(LoyaltyTierId::generate(), 'Gold', 5000, 15));

        // 500 points is below both thresholds
        $tier = $program->getTierForPoints(500);

        self::assertNull($tier);
    }

    // -----------------------------------------------------------------------
    // getTiers — sorted when sortOrder is equal (fallback to threshold)
    // -----------------------------------------------------------------------

    #[Test]
    public function itSortsTiersByThresholdWhenSortOrderIsEqual(): void
    {
        $program = $this->createProgram();
        // Same sortOrder = 0
        $silverTier = LoyaltyTier::create(LoyaltyTierId::generate(), 'Silver', 2000, 10, null, 0);
        $bronzeTier = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 1000, 5, null, 0);

        $program->addTier($silverTier);
        $program->addTier($bronzeTier);

        $tiers = $program->getTiers();

        // Bronze (threshold=1000) should come before Silver (threshold=2000)
        self::assertSame('Bronze', $tiers[0]->name());
        self::assertSame('Silver', $tiers[1]->name());
    }

    // -----------------------------------------------------------------------
    // update — min order value change
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsEventWhenMinOrderValueChanges(): void
    {
        $program = $this->createProgram();

        $program->update(
            name: 'VIP Rewards',
            description: null,
            earningRate: $this->earningRate,
            minOrderValue: Money::of('75', 'USD'), // Changed from 50
            validityDays: null,
        );

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramUpdated::class, $events[0]);
        self::assertTrue(Money::of('75', 'USD')->equals($program->minOrderValue()));
    }

    // -----------------------------------------------------------------------
    // Getters coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesAllGetters(): void
    {
        $id = LoyaltyProgramId::generate();
        $createdAt = new \DateTimeImmutable('2024-06-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-06-15 12:30:00');

        $program = LoyaltyProgram::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            name: 'Gold Plan',
            description: 'A golden plan',
            earningRate: $this->earningRate,
            minOrderValue: $this->minOrderValue,
            redemptionRule: $this->redemptionRule,
            validityDays: 365,
            isActive: false,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertTrue($id->equals($program->id()));
        self::assertTrue($this->tenantId->equals($program->tenantId()));
        self::assertSame('Gold Plan', $program->name());
        self::assertSame('A golden plan', $program->description());
        self::assertTrue($this->earningRate->equals($program->earningRate()));
        self::assertTrue($this->minOrderValue->equals($program->minOrderValue()));
        self::assertTrue($this->redemptionRule->equals($program->redemptionRule()));
        self::assertSame(365, $program->validityDays());
        self::assertFalse($program->isActive());
        self::assertEquals($createdAt, $program->createdAt());
        self::assertEquals($updatedAt, $program->updatedAt());
    }

    // -----------------------------------------------------------------------
    // calculatePointsForOrder — exactly at minOrderValue boundary
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesPointsWhenOrderEqualsMinimumValue(): void
    {
        $program = LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'Boundary Test',
            earningRate: EarningRate::fromFloat(2.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: $this->redemptionRule,
        );

        // Order exactly at minimum (50 USD) — should earn points
        $points = $program->calculatePointsForOrder(Money::of('50', 'USD'));

        // 50 * 2.0 = 100 points
        self::assertSame(100, $points);
    }
}
