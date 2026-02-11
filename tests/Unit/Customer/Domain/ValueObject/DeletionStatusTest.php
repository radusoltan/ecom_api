<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\DeletionStatus;
use PHPUnit\Framework\TestCase;

final class DeletionStatusTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = DeletionStatus::cases();

        self::assertCount(6, $cases);
        self::assertContains(DeletionStatus::PENDING, $cases);
        self::assertContains(DeletionStatus::CONFIRMED, $cases);
        self::assertContains(DeletionStatus::PROCESSING, $cases);
        self::assertContains(DeletionStatus::COMPLETED, $cases);
        self::assertContains(DeletionStatus::CANCELLED, $cases);
        self::assertContains(DeletionStatus::ON_HOLD, $cases);
    }

    public function testLabel(): void
    {
        self::assertEquals('Pending Confirmation', DeletionStatus::PENDING->label());
        self::assertEquals('Confirmed (Scheduled)', DeletionStatus::CONFIRMED->label());
        self::assertEquals('Processing', DeletionStatus::PROCESSING->label());
        self::assertEquals('Completed', DeletionStatus::COMPLETED->label());
        self::assertEquals('Cancelled', DeletionStatus::CANCELLED->label());
        self::assertEquals('On Hold (Legal)', DeletionStatus::ON_HOLD->label());
    }

    public function testCanTransitionFromPendingToConfirmed(): void
    {
        self::assertTrue(DeletionStatus::PENDING->canTransitionTo(DeletionStatus::CONFIRMED));
    }

    public function testCanTransitionFromPendingToCancelled(): void
    {
        self::assertTrue(DeletionStatus::PENDING->canTransitionTo(DeletionStatus::CANCELLED));
    }

    public function testCannotTransitionFromPendingToProcessing(): void
    {
        self::assertFalse(DeletionStatus::PENDING->canTransitionTo(DeletionStatus::PROCESSING));
    }

    public function testCanTransitionFromConfirmedToProcessing(): void
    {
        self::assertTrue(DeletionStatus::CONFIRMED->canTransitionTo(DeletionStatus::PROCESSING));
    }

    public function testCanTransitionFromConfirmedToCancelled(): void
    {
        self::assertTrue(DeletionStatus::CONFIRMED->canTransitionTo(DeletionStatus::CANCELLED));
    }

    public function testCanTransitionFromConfirmedToOnHold(): void
    {
        self::assertTrue(DeletionStatus::CONFIRMED->canTransitionTo(DeletionStatus::ON_HOLD));
    }

    public function testCanTransitionFromProcessingToCompleted(): void
    {
        self::assertTrue(DeletionStatus::PROCESSING->canTransitionTo(DeletionStatus::COMPLETED));
    }

    public function testCannotTransitionFromProcessingToCancelled(): void
    {
        self::assertFalse(DeletionStatus::PROCESSING->canTransitionTo(DeletionStatus::CANCELLED));
    }

    public function testCanTransitionFromOnHoldToConfirmed(): void
    {
        self::assertTrue(DeletionStatus::ON_HOLD->canTransitionTo(DeletionStatus::CONFIRMED));
    }

    public function testCanTransitionFromOnHoldToCancelled(): void
    {
        self::assertTrue(DeletionStatus::ON_HOLD->canTransitionTo(DeletionStatus::CANCELLED));
    }

    public function testCannotTransitionFromOnHoldToProcessing(): void
    {
        self::assertFalse(DeletionStatus::ON_HOLD->canTransitionTo(DeletionStatus::PROCESSING));
    }

    public function testCannotTransitionFromCompletedToAnyState(): void
    {
        self::assertFalse(DeletionStatus::COMPLETED->canTransitionTo(DeletionStatus::PENDING));
        self::assertFalse(DeletionStatus::COMPLETED->canTransitionTo(DeletionStatus::CONFIRMED));
        self::assertFalse(DeletionStatus::COMPLETED->canTransitionTo(DeletionStatus::PROCESSING));
        self::assertFalse(DeletionStatus::COMPLETED->canTransitionTo(DeletionStatus::CANCELLED));
        self::assertFalse(DeletionStatus::COMPLETED->canTransitionTo(DeletionStatus::ON_HOLD));
    }

    public function testCannotTransitionFromCancelledToAnyState(): void
    {
        self::assertFalse(DeletionStatus::CANCELLED->canTransitionTo(DeletionStatus::PENDING));
        self::assertFalse(DeletionStatus::CANCELLED->canTransitionTo(DeletionStatus::CONFIRMED));
        self::assertFalse(DeletionStatus::CANCELLED->canTransitionTo(DeletionStatus::PROCESSING));
        self::assertFalse(DeletionStatus::CANCELLED->canTransitionTo(DeletionStatus::COMPLETED));
        self::assertFalse(DeletionStatus::CANCELLED->canTransitionTo(DeletionStatus::ON_HOLD));
    }

    public function testIsPending(): void
    {
        self::assertTrue(DeletionStatus::PENDING->isPending());
        self::assertFalse(DeletionStatus::CONFIRMED->isPending());
    }

    public function testIsConfirmed(): void
    {
        self::assertTrue(DeletionStatus::CONFIRMED->isConfirmed());
        self::assertFalse(DeletionStatus::PENDING->isConfirmed());
    }

    public function testIsProcessing(): void
    {
        self::assertTrue(DeletionStatus::PROCESSING->isProcessing());
        self::assertFalse(DeletionStatus::CONFIRMED->isProcessing());
    }

    public function testIsCompleted(): void
    {
        self::assertTrue(DeletionStatus::COMPLETED->isCompleted());
        self::assertFalse(DeletionStatus::PROCESSING->isCompleted());
    }

    public function testIsCancelled(): void
    {
        self::assertTrue(DeletionStatus::CANCELLED->isCancelled());
        self::assertFalse(DeletionStatus::PENDING->isCancelled());
    }

    public function testIsOnHold(): void
    {
        self::assertTrue(DeletionStatus::ON_HOLD->isOnHold());
        self::assertFalse(DeletionStatus::CONFIRMED->isOnHold());
    }

    public function testIsTerminal(): void
    {
        self::assertFalse(DeletionStatus::PENDING->isTerminal());
        self::assertFalse(DeletionStatus::CONFIRMED->isTerminal());
        self::assertFalse(DeletionStatus::PROCESSING->isTerminal());
        self::assertTrue(DeletionStatus::COMPLETED->isTerminal());
        self::assertTrue(DeletionStatus::CANCELLED->isTerminal());
        self::assertFalse(DeletionStatus::ON_HOLD->isTerminal());
    }

    public function testValue(): void
    {
        self::assertEquals('pending', DeletionStatus::PENDING->value);
        self::assertEquals('confirmed', DeletionStatus::CONFIRMED->value);
        self::assertEquals('processing', DeletionStatus::PROCESSING->value);
        self::assertEquals('completed', DeletionStatus::COMPLETED->value);
        self::assertEquals('cancelled', DeletionStatus::CANCELLED->value);
        self::assertEquals('on_hold', DeletionStatus::ON_HOLD->value);
    }
}
