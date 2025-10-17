<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\ValueObject;

use App\Order\Domain\ValueObject\FulfillmentStatus;
use PHPUnit\Framework\TestCase;

final class FulfillmentStatusTest extends TestCase
{
    public function testCanCreatePendingStatus(): void
    {
        $status = FulfillmentStatus::pending();

        $this->assertSame('pending', $status->value());
        $this->assertFalse($status->isFinalState());
    }

    public function testCanCreateAssignedStatus(): void
    {
        $status = FulfillmentStatus::assigned();

        $this->assertSame('assigned', $status->value());
        $this->assertFalse($status->isFinalState());
    }

    public function testCanCreatePickingStatus(): void
    {
        $status = FulfillmentStatus::picking();

        $this->assertSame('picking', $status->value());
        $this->assertFalse($status->isFinalState());
    }

    public function testCanCreatePackingStatus(): void
    {
        $status = FulfillmentStatus::packing();

        $this->assertSame('packing', $status->value());
        $this->assertFalse($status->isFinalState());
    }

    public function testCanCreateShippingStatus(): void
    {
        $status = FulfillmentStatus::shipping();

        $this->assertSame('shipping', $status->value());
        $this->assertFalse($status->isFinalState());
    }

    public function testCanCreateDeliveredStatus(): void
    {
        $status = FulfillmentStatus::delivered();

        $this->assertSame('delivered', $status->value());
        $this->assertTrue($status->isFinalState());
    }

    public function testCanCreateCancelledStatus(): void
    {
        $status = FulfillmentStatus::cancelled();

        $this->assertSame('cancelled', $status->value());
        $this->assertTrue($status->isFinalState());
    }

    public function testCanCreateFailedStatus(): void
    {
        $status = FulfillmentStatus::failed();

        $this->assertSame('failed', $status->value());
        $this->assertTrue($status->isFinalState());
    }

    public function testCanCreateFromString(): void
    {
        $status = FulfillmentStatus::fromString('picking');

        $this->assertSame('picking', $status->value());
    }

    public function testThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid fulfillment status: invalid_status');

        FulfillmentStatus::fromString('invalid_status');
    }

    public function testCanTransitionFromPendingToAssigned(): void
    {
        $pending = FulfillmentStatus::pending();
        $assigned = FulfillmentStatus::assigned();

        $this->assertTrue($pending->canTransitionTo($assigned));
    }

    public function testCanTransitionFromAssignedToPicking(): void
    {
        $assigned = FulfillmentStatus::assigned();
        $picking = FulfillmentStatus::picking();

        $this->assertTrue($assigned->canTransitionTo($picking));
    }

    public function testCanTransitionFromPickingToPacking(): void
    {
        $picking = FulfillmentStatus::picking();
        $packing = FulfillmentStatus::packing();

        $this->assertTrue($picking->canTransitionTo($packing));
    }

    public function testCanTransitionFromPackingToShipping(): void
    {
        $packing = FulfillmentStatus::packing();
        $shipping = FulfillmentStatus::shipping();

        $this->assertTrue($packing->canTransitionTo($shipping));
    }

    public function testCanTransitionFromShippingToDelivered(): void
    {
        $shipping = FulfillmentStatus::shipping();
        $delivered = FulfillmentStatus::delivered();

        $this->assertTrue($shipping->canTransitionTo($delivered));
    }

    public function testCanCancelFromAnyNonFinalState(): void
    {
        $cancelled = FulfillmentStatus::cancelled();

        $this->assertTrue(FulfillmentStatus::pending()->canTransitionTo($cancelled));
        $this->assertTrue(FulfillmentStatus::assigned()->canTransitionTo($cancelled));
        $this->assertTrue(FulfillmentStatus::picking()->canTransitionTo($cancelled));
        $this->assertTrue(FulfillmentStatus::packing()->canTransitionTo($cancelled));
    }

    public function testCannotTransitionFromFinalState(): void
    {
        $delivered = FulfillmentStatus::delivered();
        $picking = FulfillmentStatus::picking();

        $this->assertFalse($delivered->canTransitionTo($picking));
    }

    public function testCannotTransitionBackwards(): void
    {
        $packing = FulfillmentStatus::packing();
        $picking = FulfillmentStatus::picking();

        $this->assertFalse($packing->canTransitionTo($picking));
    }

    public function testCannotTransitionFromShippingToCancelled(): void
    {
        $shipping = FulfillmentStatus::shipping();
        $cancelled = FulfillmentStatus::cancelled();

        $this->assertFalse($shipping->canTransitionTo($cancelled));
    }

    public function testEqualsReturnsTrueForSameStatus(): void
    {
        $status1 = FulfillmentStatus::picking();
        $status2 = FulfillmentStatus::picking();

        $this->assertTrue($status1->equals($status2));
    }

    public function testEqualsReturnsFalseForDifferentStatus(): void
    {
        $status1 = FulfillmentStatus::picking();
        $status2 = FulfillmentStatus::packing();

        $this->assertFalse($status1->equals($status2));
    }

    public function testGetAllStatuses(): void
    {
        $statuses = FulfillmentStatus::getAllStatuses();

        $this->assertCount(8, $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('assigned', $statuses);
        $this->assertContains('picking', $statuses);
        $this->assertContains('packing', $statuses);
        $this->assertContains('shipping', $statuses);
        $this->assertContains('delivered', $statuses);
        $this->assertContains('cancelled', $statuses);
        $this->assertContains('failed', $statuses);
    }
}
