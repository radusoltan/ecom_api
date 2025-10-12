<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    public function testPendingStatus(): void
    {
        $status = PaymentStatus::pending();

        $this->assertSame('pending', $status->value());
        $this->assertTrue($status->isPending());
        $this->assertFalse($status->isAuthorized());
        $this->assertFalse($status->isCaptured());
        $this->assertFalse($status->isRefunded());
        $this->assertFalse($status->isFailed());
        $this->assertFalse($status->isCancelled());
    }

    public function testAuthorizedStatus(): void
    {
        $status = PaymentStatus::authorized();

        $this->assertSame('authorized', $status->value());
        $this->assertTrue($status->isAuthorized());
        $this->assertFalse($status->isPending());
    }

    public function testCapturedStatus(): void
    {
        $status = PaymentStatus::captured();

        $this->assertSame('captured', $status->value());
        $this->assertTrue($status->isCaptured());
    }

    public function testRefundedStatus(): void
    {
        $status = PaymentStatus::refunded();

        $this->assertSame('refunded', $status->value());
        $this->assertTrue($status->isRefunded());
    }

    public function testFailedStatus(): void
    {
        $status = PaymentStatus::failed();

        $this->assertSame('failed', $status->value());
        $this->assertTrue($status->isFailed());
    }

    public function testCancelledStatus(): void
    {
        $status = PaymentStatus::cancelled();

        $this->assertSame('cancelled', $status->value());
        $this->assertTrue($status->isCancelled());
    }

    public function testFromString(): void
    {
        $status = PaymentStatus::fromString('pending');

        $this->assertTrue($status->isPending());
    }

    public function testFromStringWithInvalidStatusThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid payment status/');

        PaymentStatus::fromString('invalid_status');
    }

    public function testEquals(): void
    {
        $status1 = PaymentStatus::pending();
        $status2 = PaymentStatus::pending();
        $status3 = PaymentStatus::authorized();

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    // State Machine Transition Tests
    public function testPendingCanTransitionToAuthorized(): void
    {
        $pending = PaymentStatus::pending();
        $authorized = PaymentStatus::authorized();

        $this->assertTrue($pending->canTransitionTo($authorized));
    }

    public function testPendingCanTransitionToFailed(): void
    {
        $pending = PaymentStatus::pending();
        $failed = PaymentStatus::failed();

        $this->assertTrue($pending->canTransitionTo($failed));
    }

    public function testPendingCanTransitionToCancelled(): void
    {
        $pending = PaymentStatus::pending();
        $cancelled = PaymentStatus::cancelled();

        $this->assertTrue($pending->canTransitionTo($cancelled));
    }

    public function testPendingCannotTransitionToCaptured(): void
    {
        $pending = PaymentStatus::pending();
        $captured = PaymentStatus::captured();

        $this->assertFalse($pending->canTransitionTo($captured));
    }

    public function testPendingCannotTransitionToRefunded(): void
    {
        $pending = PaymentStatus::pending();
        $refunded = PaymentStatus::refunded();

        $this->assertFalse($pending->canTransitionTo($refunded));
    }

    public function testAuthorizedCanTransitionToCaptured(): void
    {
        $authorized = PaymentStatus::authorized();
        $captured = PaymentStatus::captured();

        $this->assertTrue($authorized->canTransitionTo($captured));
    }

    public function testAuthorizedCanTransitionToCancelled(): void
    {
        $authorized = PaymentStatus::authorized();
        $cancelled = PaymentStatus::cancelled();

        $this->assertTrue($authorized->canTransitionTo($cancelled));
    }

    public function testAuthorizedCanTransitionToFailed(): void
    {
        $authorized = PaymentStatus::authorized();
        $failed = PaymentStatus::failed();

        $this->assertTrue($authorized->canTransitionTo($failed));
    }

    public function testAuthorizedCannotTransitionToPending(): void
    {
        $authorized = PaymentStatus::authorized();
        $pending = PaymentStatus::pending();

        $this->assertFalse($authorized->canTransitionTo($pending));
    }

    public function testAuthorizedCannotTransitionToRefunded(): void
    {
        $authorized = PaymentStatus::authorized();
        $refunded = PaymentStatus::refunded();

        $this->assertFalse($authorized->canTransitionTo($refunded));
    }

    public function testCapturedCanTransitionToRefunded(): void
    {
        $captured = PaymentStatus::captured();
        $refunded = PaymentStatus::refunded();

        $this->assertTrue($captured->canTransitionTo($refunded));
    }

    public function testCapturedCannotTransitionToPending(): void
    {
        $captured = PaymentStatus::captured();
        $pending = PaymentStatus::pending();

        $this->assertFalse($captured->canTransitionTo($pending));
    }

    public function testCapturedCannotTransitionToAuthorized(): void
    {
        $captured = PaymentStatus::captured();
        $authorized = PaymentStatus::authorized();

        $this->assertFalse($captured->canTransitionTo($authorized));
    }

    public function testCapturedCannotTransitionToCancelled(): void
    {
        $captured = PaymentStatus::captured();
        $cancelled = PaymentStatus::cancelled();

        $this->assertFalse($captured->canTransitionTo($cancelled));
    }

    public function testCapturedCannotTransitionToFailed(): void
    {
        $captured = PaymentStatus::captured();
        $failed = PaymentStatus::failed();

        $this->assertFalse($captured->canTransitionTo($failed));
    }

    public function testRefundedCannotTransitionToAnyStatus(): void
    {
        $refunded = PaymentStatus::refunded();

        $this->assertFalse($refunded->canTransitionTo(PaymentStatus::pending()));
        $this->assertFalse($refunded->canTransitionTo(PaymentStatus::authorized()));
        $this->assertFalse($refunded->canTransitionTo(PaymentStatus::captured()));
        $this->assertFalse($refunded->canTransitionTo(PaymentStatus::failed()));
        $this->assertFalse($refunded->canTransitionTo(PaymentStatus::cancelled()));
    }

    public function testFailedCannotTransitionToAnyStatus(): void
    {
        $failed = PaymentStatus::failed();

        $this->assertFalse($failed->canTransitionTo(PaymentStatus::pending()));
        $this->assertFalse($failed->canTransitionTo(PaymentStatus::authorized()));
        $this->assertFalse($failed->canTransitionTo(PaymentStatus::captured()));
        $this->assertFalse($failed->canTransitionTo(PaymentStatus::refunded()));
        $this->assertFalse($failed->canTransitionTo(PaymentStatus::cancelled()));
    }

    public function testCancelledCannotTransitionToAnyStatus(): void
    {
        $cancelled = PaymentStatus::cancelled();

        $this->assertFalse($cancelled->canTransitionTo(PaymentStatus::pending()));
        $this->assertFalse($cancelled->canTransitionTo(PaymentStatus::authorized()));
        $this->assertFalse($cancelled->canTransitionTo(PaymentStatus::captured()));
        $this->assertFalse($cancelled->canTransitionTo(PaymentStatus::refunded()));
        $this->assertFalse($cancelled->canTransitionTo(PaymentStatus::failed()));
    }
}
