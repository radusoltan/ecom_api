<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Event;

use App\Customer\Domain\Event\LoyaltyPointsAwarded;
use App\Customer\Domain\Event\LoyaltyPointsRedeemed;
use App\Customer\Domain\Event\LoyaltyProgramActivated;
use App\Customer\Domain\Event\LoyaltyProgramCreated;
use App\Customer\Domain\Event\LoyaltyProgramDeactivated;
use App\Customer\Domain\Event\LoyaltyProgramUpdated;
use App\Customer\Domain\Event\LoyaltyTierAdded;
use App\Customer\Domain\Event\LoyaltyTierRemoved;
use App\Customer\Domain\Event\LoyaltyTierUpdated;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoyaltyPointsAwarded::class)]
#[CoversClass(LoyaltyPointsRedeemed::class)]
#[CoversClass(LoyaltyProgramCreated::class)]
#[CoversClass(LoyaltyProgramUpdated::class)]
#[CoversClass(LoyaltyProgramActivated::class)]
#[CoversClass(LoyaltyProgramDeactivated::class)]
#[CoversClass(LoyaltyTierAdded::class)]
#[CoversClass(LoyaltyTierUpdated::class)]
#[CoversClass(LoyaltyTierRemoved::class)]
final class LoyaltyEventsTest extends TestCase
{
    private LoyaltyProgramId $programId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->programId = LoyaltyProgramId::generate();
        $this->tenantId = TenantId::generate();
    }

    // ---------------------------------------------------------------
    // LoyaltyPointsAwarded
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyPointsAwarded(): void
    {
        $customerId = CustomerId::generate();

        $event = new LoyaltyPointsAwarded(
            customerId: $customerId,
            pointsAwarded: 150,
            newTotalPoints: 650,
            reason: 'Order completed',
        );

        self::assertTrue($customerId->equals($event->customerId()));
        self::assertSame(150, $event->pointsAwarded());
        self::assertSame(650, $event->newTotalPoints());
        self::assertSame('Order completed', $event->reason());
    }

    // ---------------------------------------------------------------
    // LoyaltyPointsRedeemed
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllPublicPropertiesOnLoyaltyPointsRedeemed(): void
    {
        $customerId = CustomerId::generate();
        $discountMoney = Money::of('10.00', 'EUR');
        $occurredOn = new \DateTimeImmutable('2025-04-01 11:00:00');

        $event = new LoyaltyPointsRedeemed(
            customerId: $customerId,
            pointsRedeemed: 200,
            remainingBalance: 450,
            discountAmount: $discountMoney,
            orderId: 'order-uuid-123',
            occurredOn: $occurredOn,
        );

        self::assertTrue($customerId->equals($event->customerId));
        self::assertSame(200, $event->pointsRedeemed);
        self::assertSame(450, $event->remainingBalance);
        self::assertTrue($discountMoney->equals($event->discountAmount));
        self::assertSame('order-uuid-123', $event->orderId);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    #[Test]
    public function itAllowsNullOrderIdOnLoyaltyPointsRedeemed(): void
    {
        $event = new LoyaltyPointsRedeemed(
            customerId: CustomerId::generate(),
            pointsRedeemed: 100,
            remainingBalance: 0,
            discountAmount: Money::of('5.00', 'EUR'),
            orderId: null,
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertNull($event->orderId);
    }

    // ---------------------------------------------------------------
    // LoyaltyProgramCreated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyProgramCreated(): void
    {
        $earningRate = EarningRate::fromFloat(1.5);
        $occurredAt = new \DateTimeImmutable('2025-01-01 00:00:00');

        $event = new LoyaltyProgramCreated(
            programId: $this->programId,
            tenantId: $this->tenantId,
            name: 'Gold Rewards',
            earningRate: $earningRate,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->programId->equals($event->programId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame('Gold Rewards', $event->name());
        self::assertTrue($earningRate->equals($event->earningRate()));
        self::assertSame($occurredAt, $event->occurredAt());
    }

    // ---------------------------------------------------------------
    // LoyaltyProgramUpdated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyProgramUpdated(): void
    {
        $changes = ['name' => ['old' => 'Bronze', 'new' => 'Silver']];
        $occurredAt = new \DateTimeImmutable();

        $event = new LoyaltyProgramUpdated(
            programId: $this->programId,
            tenantId: $this->tenantId,
            changes: $changes,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->programId->equals($event->programId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame($changes, $event->changes());
        self::assertSame($occurredAt, $event->occurredAt());
    }

    #[Test]
    public function itAllowsEmptyChangesArrayOnLoyaltyProgramUpdated(): void
    {
        $event = new LoyaltyProgramUpdated(
            programId: $this->programId,
            tenantId: $this->tenantId,
            changes: [],
            occurredAt: new \DateTimeImmutable(),
        );

        self::assertSame([], $event->changes());
    }

    // ---------------------------------------------------------------
    // LoyaltyProgramActivated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyProgramActivated(): void
    {
        $occurredAt = new \DateTimeImmutable('2025-02-01 08:00:00');

        $event = new LoyaltyProgramActivated(
            programId: $this->programId,
            tenantId: $this->tenantId,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->programId->equals($event->programId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame($occurredAt, $event->occurredAt());
    }

    // ---------------------------------------------------------------
    // LoyaltyProgramDeactivated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyProgramDeactivated(): void
    {
        $occurredAt = new \DateTimeImmutable('2025-03-01 18:00:00');

        $event = new LoyaltyProgramDeactivated(
            programId: $this->programId,
            tenantId: $this->tenantId,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->programId->equals($event->programId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame($occurredAt, $event->occurredAt());
    }

    // ---------------------------------------------------------------
    // LoyaltyTierAdded
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyTierAdded(): void
    {
        $tierId = LoyaltyTierId::generate();
        $occurredAt = new \DateTimeImmutable();

        $event = new LoyaltyTierAdded(
            programId: $this->programId,
            tierId: $tierId,
            tierName: 'Platinum',
            threshold: 5000,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->programId->equals($event->programId()));
        self::assertTrue($tierId->equals($event->tierId()));
        self::assertSame('Platinum', $event->tierName());
        self::assertSame(5000, $event->threshold());
        self::assertSame($occurredAt, $event->occurredAt());
    }

    // ---------------------------------------------------------------
    // LoyaltyTierUpdated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyTierUpdated(): void
    {
        $tierId = LoyaltyTierId::generate();
        $changes = ['threshold' => ['old' => 1000, 'new' => 1500]];
        $occurredAt = new \DateTimeImmutable();

        $event = new LoyaltyTierUpdated(
            programId: $this->programId,
            tierId: $tierId,
            changes: $changes,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->programId->equals($event->programId()));
        self::assertTrue($tierId->equals($event->tierId()));
        self::assertSame($changes, $event->changes());
        self::assertSame($occurredAt, $event->occurredAt());
    }

    // ---------------------------------------------------------------
    // LoyaltyTierRemoved
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnLoyaltyTierRemoved(): void
    {
        $tierId = LoyaltyTierId::generate();
        $occurredAt = new \DateTimeImmutable();

        $event = new LoyaltyTierRemoved(
            programId: $this->programId,
            tierId: $tierId,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->programId->equals($event->programId()));
        self::assertTrue($tierId->equals($event->tierId()));
        self::assertSame($occurredAt, $event->occurredAt());
    }
}
