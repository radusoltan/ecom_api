<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    // =============================================
    // Test Status Creation via Named Constructors
    // =============================================

    public function testItCreatesPendingStatus(): void
    {
        $status = OrderStatus::pending();
        $this->assertTrue($status->isPending());
        $this->assertSame('pending', $status->value());
    }

    public function testItCreatesProcessingStatus(): void
    {
        $status = OrderStatus::processing();
        $this->assertTrue($status->isProcessing());
        $this->assertSame('processing', $status->value());
    }

    public function testItCreatesShippedStatus(): void
    {
        $status = OrderStatus::shipped();
        $this->assertTrue($status->isShipped());
        $this->assertSame('shipped', $status->value());
    }

    public function testItCreatesDeliveredStatus(): void
    {
        $status = OrderStatus::delivered();
        $this->assertTrue($status->isDelivered());
        $this->assertSame('delivered', $status->value());
    }

    public function testItCreatesCancelledStatus(): void
    {
        $status = OrderStatus::cancelled();
        $this->assertTrue($status->isCancelled());
        $this->assertSame('cancelled', $status->value());
    }

    // =============================================
    // Test Status Creation from String
    // =============================================

    public function testItCreatesStatusFromValidString(): void
    {
        $status = OrderStatus::fromString('pending');
        $this->assertTrue($status->isPending());
    }

    public function testItThrowsExceptionForInvalidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order status');

        OrderStatus::fromString('invalid_status');
    }

    public function testItThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrderStatus::fromString('');
    }

    // =============================================
    // Test State Checkers
    // =============================================

    public function testIsPendingReturnsTrueOnlyForPendingStatus(): void
    {
        $this->assertTrue(OrderStatus::pending()->isPending());
        $this->assertFalse(OrderStatus::processing()->isPending());
        $this->assertFalse(OrderStatus::shipped()->isPending());
        $this->assertFalse(OrderStatus::delivered()->isPending());
        $this->assertFalse(OrderStatus::cancelled()->isPending());
    }

    public function testIsProcessingReturnsTrueOnlyForProcessingStatus(): void
    {
        $this->assertFalse(OrderStatus::pending()->isProcessing());
        $this->assertTrue(OrderStatus::processing()->isProcessing());
        $this->assertFalse(OrderStatus::shipped()->isProcessing());
        $this->assertFalse(OrderStatus::delivered()->isProcessing());
        $this->assertFalse(OrderStatus::cancelled()->isProcessing());
    }

    public function testIsShippedReturnsTrueOnlyForShippedStatus(): void
    {
        $this->assertFalse(OrderStatus::pending()->isShipped());
        $this->assertFalse(OrderStatus::processing()->isShipped());
        $this->assertTrue(OrderStatus::shipped()->isShipped());
        $this->assertFalse(OrderStatus::delivered()->isShipped());
        $this->assertFalse(OrderStatus::cancelled()->isShipped());
    }

    public function testIsDeliveredReturnsTrueOnlyForDeliveredStatus(): void
    {
        $this->assertFalse(OrderStatus::pending()->isDelivered());
        $this->assertFalse(OrderStatus::processing()->isDelivered());
        $this->assertFalse(OrderStatus::shipped()->isDelivered());
        $this->assertTrue(OrderStatus::delivered()->isDelivered());
        $this->assertFalse(OrderStatus::cancelled()->isDelivered());
    }

    public function testIsCancelledReturnsTrueOnlyForCancelledStatus(): void
    {
        $this->assertFalse(OrderStatus::pending()->isCancelled());
        $this->assertFalse(OrderStatus::processing()->isCancelled());
        $this->assertFalse(OrderStatus::shipped()->isCancelled());
        $this->assertFalse(OrderStatus::delivered()->isCancelled());
        $this->assertTrue(OrderStatus::cancelled()->isCancelled());
    }

    public function testIsFinalReturnsTrueForDeliveredAndCancelled(): void
    {
        $this->assertFalse(OrderStatus::pending()->isFinal());
        $this->assertFalse(OrderStatus::processing()->isFinal());
        $this->assertFalse(OrderStatus::shipped()->isFinal());
        $this->assertTrue(OrderStatus::delivered()->isFinal());
        $this->assertTrue(OrderStatus::cancelled()->isFinal());
    }

    // =============================================
    // Test Valid Transitions
    // =============================================

    public function testPendingCanTransitionToProcessingOrCancelled(): void
    {
        $pending = OrderStatus::pending();
        $this->assertTrue($pending->canTransitionTo(OrderStatus::processing()));
        $this->assertTrue($pending->canTransitionTo(OrderStatus::cancelled()));
        $this->assertFalse($pending->canTransitionTo(OrderStatus::shipped()));
        $this->assertFalse($pending->canTransitionTo(OrderStatus::delivered()));
    }

    public function testProcessingCanTransitionToShippedOrCancelled(): void
    {
        $processing = OrderStatus::processing();
        $this->assertTrue($processing->canTransitionTo(OrderStatus::shipped()));
        $this->assertTrue($processing->canTransitionTo(OrderStatus::cancelled()));
        $this->assertFalse($processing->canTransitionTo(OrderStatus::pending()));
        $this->assertFalse($processing->canTransitionTo(OrderStatus::delivered()));
    }

    public function testShippedCanOnlyTransitionToDelivered(): void
    {
        $shipped = OrderStatus::shipped();
        $this->assertTrue($shipped->canTransitionTo(OrderStatus::delivered()));
        $this->assertFalse($shipped->canTransitionTo(OrderStatus::pending()));
        $this->assertFalse($shipped->canTransitionTo(OrderStatus::processing()));
        $this->assertFalse($shipped->canTransitionTo(OrderStatus::cancelled()));
    }

    public function testDeliveredCannotTransitionToAnyStatus(): void
    {
        $delivered = OrderStatus::delivered();
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::pending()));
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::processing()));
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::shipped()));
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::cancelled()));
    }

    public function testCancelledCannotTransitionToAnyStatus(): void
    {
        $cancelled = OrderStatus::cancelled();
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::pending()));
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::processing()));
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::shipped()));
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::delivered()));
    }

    // =============================================
    // Test Equals Method
    // =============================================

    public function testEqualsReturnsTrueForSameStatus(): void
    {
        $pending1 = OrderStatus::pending();
        $pending2 = OrderStatus::pending();
        $this->assertTrue($pending1->equals($pending2));
    }

    public function testEqualsReturnsFalseForDifferentStatus(): void
    {
        $pending = OrderStatus::pending();
        $processing = OrderStatus::processing();
        $this->assertFalse($pending->equals($processing));
    }

    // =============================================
    // Test String Representation
    // =============================================

    public function testToStringReturnsStatusValue(): void
    {
        $this->assertSame('pending', (string) OrderStatus::pending());
        $this->assertSame('processing', (string) OrderStatus::processing());
        $this->assertSame('shipped', (string) OrderStatus::shipped());
        $this->assertSame('delivered', (string) OrderStatus::delivered());
        $this->assertSame('cancelled', (string) OrderStatus::cancelled());
    }
}
