<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Event;

use App\Customer\Domain\Event\AccountDeletionCancelled;
use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Customer\Domain\Event\AccountDeletionConfirmed;
use App\Customer\Domain\Event\AccountDeletionHeld;
use App\Customer\Domain\Event\AccountDeletionRequested;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountDeletionRequested::class)]
#[CoversClass(AccountDeletionConfirmed::class)]
#[CoversClass(AccountDeletionCancelled::class)]
#[CoversClass(AccountDeletionCompleted::class)]
#[CoversClass(AccountDeletionHeld::class)]
final class AccountDeletionEventsTest extends TestCase
{
    private CustomerId $customerId;
    private TenantId $tenantId;
    private DeletionRequestId $requestId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
        $this->requestId = DeletionRequestId::generate();
    }

    // ---------------------------------------------------------------
    // AccountDeletionRequested
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnAccountDeletionRequested(): void
    {
        $occurredAt = new \DateTimeImmutable('2025-06-01 12:00:00');

        $event = new AccountDeletionRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            reason: 'User requested data removal',
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->requestId->equals($event->requestId()));
        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame('User requested data removal', $event->reason());
        self::assertSame($occurredAt, $event->occurredOn());
    }

    #[Test]
    public function itAllowsNullReasonOnAccountDeletionRequested(): void
    {
        $event = new AccountDeletionRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            reason: null,
        );

        self::assertNull($event->reason());
    }

    #[Test]
    public function itDefaultsOccurredAtToNowOnAccountDeletionRequested(): void
    {
        $before = new \DateTimeImmutable();

        $event = new AccountDeletionRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            reason: null,
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn()->getTimestamp());
    }

    #[Test]
    public function itImplementsDomainEventOnAccountDeletionRequested(): void
    {
        $event = new AccountDeletionRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            reason: null,
        );

        self::assertInstanceOf(\App\Shared\Domain\Event\DomainEvent::class, $event);
    }

    // ---------------------------------------------------------------
    // AccountDeletionConfirmed
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnAccountDeletionConfirmed(): void
    {
        $scheduledFor = new \DateTimeImmutable('+30 days');

        $event = new AccountDeletionConfirmed(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            scheduledFor: $scheduledFor,
        );

        self::assertTrue($this->requestId->equals($event->requestId()));
        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame($scheduledFor, $event->scheduledFor());
    }

    // ---------------------------------------------------------------
    // AccountDeletionCancelled
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnAccountDeletionCancelled(): void
    {
        $event = new AccountDeletionCancelled(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        self::assertTrue($this->requestId->equals($event->requestId()));
        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
    }

    // ---------------------------------------------------------------
    // AccountDeletionCompleted
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnAccountDeletionCompleted(): void
    {
        $occurredAt = new \DateTimeImmutable('2025-07-01 08:00:00');

        $event = new AccountDeletionCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->requestId->equals($event->requestId()));
        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame($occurredAt, $event->occurredOn());
    }

    #[Test]
    public function itDefaultsOccurredAtToNowOnAccountDeletionCompleted(): void
    {
        $before = new \DateTimeImmutable();

        $event = new AccountDeletionCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn()->getTimestamp());
    }

    #[Test]
    public function itImplementsDomainEventOnAccountDeletionCompleted(): void
    {
        $event = new AccountDeletionCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        self::assertInstanceOf(\App\Shared\Domain\Event\DomainEvent::class, $event);
    }

    // ---------------------------------------------------------------
    // AccountDeletionHeld
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnAccountDeletionHeld(): void
    {
        $event = new AccountDeletionHeld(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            holdReason: 'Pending investigation',
        );

        self::assertTrue($this->requestId->equals($event->requestId()));
        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($this->tenantId->equals($event->tenantId()));
        self::assertSame('Pending investigation', $event->holdReason());
    }
}
