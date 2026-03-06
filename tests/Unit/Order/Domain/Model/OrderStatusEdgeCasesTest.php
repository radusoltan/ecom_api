<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\OrderStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderStatus::class)]
final class OrderStatusEdgeCasesTest extends TestCase
{
    // ---------------------------------------------------------------
    // paid status (not yet covered in existing tests)
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesPaidStatus(): void
    {
        $status = OrderStatus::paid();

        self::assertTrue($status->isPaid());
        self::assertSame('paid', $status->value());
    }

    #[Test]
    public function itCreatesPaidStatusFromString(): void
    {
        $status = OrderStatus::fromString('paid');

        self::assertTrue($status->isPaid());
    }

    #[Test]
    public function itIsPaidReturnsFalseForAllOtherStatuses(): void
    {
        self::assertFalse(OrderStatus::draft()->isPaid());
        self::assertFalse(OrderStatus::pending()->isPaid());
        self::assertFalse(OrderStatus::processing()->isPaid());
        self::assertFalse(OrderStatus::shipped()->isPaid());
        self::assertFalse(OrderStatus::delivered()->isPaid());
        self::assertFalse(OrderStatus::cancelled()->isPaid());
    }

    #[Test]
    public function itIsPaidToStringReturnsPaid(): void
    {
        self::assertSame('paid', (string) OrderStatus::paid());
    }

    // ---------------------------------------------------------------
    // paid transitions
    // ---------------------------------------------------------------

    #[Test]
    public function itPaidCanTransitionToProcessingAndCancelled(): void
    {
        $paid = OrderStatus::paid();

        self::assertTrue($paid->canTransitionTo(OrderStatus::processing()));
        self::assertTrue($paid->canTransitionTo(OrderStatus::cancelled()));
    }

    #[Test]
    public function itPaidCannotTransitionToDraftPendingShippedOrDelivered(): void
    {
        $paid = OrderStatus::paid();

        self::assertFalse($paid->canTransitionTo(OrderStatus::draft()));
        self::assertFalse($paid->canTransitionTo(OrderStatus::pending()));
        self::assertFalse($paid->canTransitionTo(OrderStatus::shipped()));
        self::assertFalse($paid->canTransitionTo(OrderStatus::delivered()));
    }

    #[Test]
    public function itPendingCanTransitionToPaid(): void
    {
        $pending = OrderStatus::pending();

        self::assertTrue($pending->canTransitionTo(OrderStatus::paid()));
    }

    #[Test]
    public function itProcessingCanTransitionBackToPaid(): void
    {
        $processing = OrderStatus::processing();

        self::assertTrue($processing->canTransitionTo(OrderStatus::paid()));
    }

    // ---------------------------------------------------------------
    // isFinal — paid is NOT final
    // ---------------------------------------------------------------

    #[Test]
    public function itPaidIsNotFinal(): void
    {
        self::assertFalse(OrderStatus::paid()->isFinal());
    }

    // ---------------------------------------------------------------
    // equals symmetry
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsSameStatusFromString(): void
    {
        $a = OrderStatus::fromString('processing');
        $b = OrderStatus::processing();

        self::assertTrue($a->equals($b));
        self::assertTrue($b->equals($a));
    }

    #[Test]
    public function itDoesNotEqualDifferentStatus(): void
    {
        $pending = OrderStatus::pending();
        $paid = OrderStatus::paid();

        self::assertFalse($pending->equals($paid));
        self::assertFalse($paid->equals($pending));
    }

    // ---------------------------------------------------------------
    // __toString for all values
    // ---------------------------------------------------------------

    #[Test]
    public function itToStringReturnsPaidForPaid(): void
    {
        self::assertSame('paid', (string) OrderStatus::fromString('paid'));
    }
}
