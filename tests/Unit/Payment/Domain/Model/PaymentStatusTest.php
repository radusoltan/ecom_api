<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    // ==================== Valid State Transitions ====================

    public function testPendingCanTransitionToAuthorized(): void
    {
        $this->assertTrue(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::AUTHORIZED));
    }

    public function testPendingCanTransitionToFailed(): void
    {
        $this->assertTrue(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::FAILED));
    }

    public function testPendingCanTransitionToCancelled(): void
    {
        $this->assertTrue(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::CANCELLED));
    }

    public function testAuthorizedCanTransitionToCaptured(): void
    {
        $this->assertTrue(PaymentStatus::AUTHORIZED->canTransitionTo(PaymentStatus::CAPTURED));
    }

    public function testAuthorizedCanTransitionToExpired(): void
    {
        $this->assertTrue(PaymentStatus::AUTHORIZED->canTransitionTo(PaymentStatus::EXPIRED));
    }

    public function testAuthorizedCanTransitionToVoided(): void
    {
        $this->assertTrue(PaymentStatus::AUTHORIZED->canTransitionTo(PaymentStatus::VOIDED));
    }

    public function testAuthorizedCanTransitionToFailed(): void
    {
        $this->assertTrue(PaymentStatus::AUTHORIZED->canTransitionTo(PaymentStatus::FAILED));
    }

    public function testCapturedCanTransitionToRefunded(): void
    {
        $this->assertTrue(PaymentStatus::CAPTURED->canTransitionTo(PaymentStatus::REFUNDED));
    }

    public function testCapturedCanTransitionToPartiallyRefunded(): void
    {
        $this->assertTrue(PaymentStatus::CAPTURED->canTransitionTo(PaymentStatus::PARTIALLY_REFUNDED));
    }

    public function testCapturedCanTransitionToDisputed(): void
    {
        $this->assertTrue(PaymentStatus::CAPTURED->canTransitionTo(PaymentStatus::DISPUTED));
    }

    public function testPartiallyRefundedCanTransitionToRefunded(): void
    {
        $this->assertTrue(PaymentStatus::PARTIALLY_REFUNDED->canTransitionTo(PaymentStatus::REFUNDED));
    }

    public function testPartiallyRefundedCanTransitionToPartiallyRefunded(): void
    {
        // Multiple partial refunds allowed
        $this->assertTrue(PaymentStatus::PARTIALLY_REFUNDED->canTransitionTo(PaymentStatus::PARTIALLY_REFUNDED));
    }

    // ==================== Invalid State Transitions ====================

    public function testPendingCannotTransitionToCaptured(): void
    {
        $this->assertFalse(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::CAPTURED));
    }

    public function testPendingCannotTransitionToRefunded(): void
    {
        $this->assertFalse(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::REFUNDED));
    }

    public function testAuthorizedCannotTransitionToCancelled(): void
    {
        $this->assertFalse(PaymentStatus::AUTHORIZED->canTransitionTo(PaymentStatus::CANCELLED));
    }

    public function testCapturedCannotTransitionToPending(): void
    {
        $this->assertFalse(PaymentStatus::CAPTURED->canTransitionTo(PaymentStatus::PENDING));
    }

    public function testRefundedCannotTransitionToPending(): void
    {
        $this->assertFalse(PaymentStatus::REFUNDED->canTransitionTo(PaymentStatus::PENDING));
    }

    public function testRefundedCannotTransitionToAuthorized(): void
    {
        $this->assertFalse(PaymentStatus::REFUNDED->canTransitionTo(PaymentStatus::AUTHORIZED));
    }

    public function testFailedCannotTransitionToPending(): void
    {
        $this->assertFalse(PaymentStatus::FAILED->canTransitionTo(PaymentStatus::PENDING));
    }

    public function testCancelledCannotTransitionToAuthorized(): void
    {
        $this->assertFalse(PaymentStatus::CANCELLED->canTransitionTo(PaymentStatus::AUTHORIZED));
    }

    // ==================== Transition Method ====================

    public function testTransitionToReturnsNewStatusOnValidTransition(): void
    {
        // Arrange
        $status = PaymentStatus::PENDING;

        // Act
        $newStatus = $status->transitionTo(PaymentStatus::AUTHORIZED);

        // Assert
        $this->assertSame(PaymentStatus::AUTHORIZED, $newStatus);
    }

    public function testTransitionToThrowsExceptionOnInvalidTransition(): void
    {
        // Arrange
        $status = PaymentStatus::PENDING;

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot transition from pending to captured');

        // Act
        $status->transitionTo(PaymentStatus::CAPTURED);
    }

    public function testTransitionToThrowsExceptionFromTerminalState(): void
    {
        // Arrange
        $status = PaymentStatus::REFUNDED;

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('none (terminal state)');

        // Act
        $status->transitionTo(PaymentStatus::PENDING);
    }

    // ==================== Terminal States ====================

    public function testIsFinalReturnsTrueForRefunded(): void
    {
        $this->assertTrue(PaymentStatus::REFUNDED->isFinal());
    }

    public function testIsFinalReturnsTrueForFailed(): void
    {
        $this->assertTrue(PaymentStatus::FAILED->isFinal());
    }

    public function testIsFinalReturnsTrueForCancelled(): void
    {
        $this->assertTrue(PaymentStatus::CANCELLED->isFinal());
    }

    public function testIsFinalReturnsTrueForExpired(): void
    {
        $this->assertTrue(PaymentStatus::EXPIRED->isFinal());
    }

    public function testIsFinalReturnsTrueForVoided(): void
    {
        $this->assertTrue(PaymentStatus::VOIDED->isFinal());
    }

    public function testIsFinalReturnsTrueForDisputed(): void
    {
        $this->assertTrue(PaymentStatus::DISPUTED->isFinal());
    }

    public function testIsFinalReturnsFalseForPending(): void
    {
        $this->assertFalse(PaymentStatus::PENDING->isFinal());
    }

    public function testIsFinalReturnsFalseForAuthorized(): void
    {
        $this->assertFalse(PaymentStatus::AUTHORIZED->isFinal());
    }

    public function testIsFinalReturnsFalseForCaptured(): void
    {
        $this->assertFalse(PaymentStatus::CAPTURED->isFinal());
    }

    public function testIsFinalReturnsFalseForPartiallyRefunded(): void
    {
        $this->assertFalse(PaymentStatus::PARTIALLY_REFUNDED->isFinal());
    }

    // ==================== Success States ====================

    public function testIsSuccessfulReturnsTrueForCaptured(): void
    {
        $this->assertTrue(PaymentStatus::CAPTURED->isSuccessful());
    }

    public function testIsSuccessfulReturnsTrueForAuthorized(): void
    {
        $this->assertTrue(PaymentStatus::AUTHORIZED->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForPending(): void
    {
        $this->assertFalse(PaymentStatus::PENDING->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForFailed(): void
    {
        $this->assertFalse(PaymentStatus::FAILED->isSuccessful());
    }

    // ==================== Individual Status Checkers ====================

    public function testIsPendingReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::PENDING->isPending());
        $this->assertFalse(PaymentStatus::AUTHORIZED->isPending());
    }

    public function testIsAuthorizedReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::AUTHORIZED->isAuthorized());
        $this->assertFalse(PaymentStatus::PENDING->isAuthorized());
    }

    public function testIsCapturedReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::CAPTURED->isCaptured());
        $this->assertFalse(PaymentStatus::AUTHORIZED->isCaptured());
    }

    public function testIsPartiallyRefundedReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::PARTIALLY_REFUNDED->isPartiallyRefunded());
        $this->assertFalse(PaymentStatus::REFUNDED->isPartiallyRefunded());
    }

    public function testIsRefundedReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::REFUNDED->isRefunded());
        $this->assertFalse(PaymentStatus::PARTIALLY_REFUNDED->isRefunded());
    }

    public function testIsFailedReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::FAILED->isFailed());
        $this->assertFalse(PaymentStatus::PENDING->isFailed());
    }

    public function testIsCancelledReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::CANCELLED->isCancelled());
        $this->assertFalse(PaymentStatus::PENDING->isCancelled());
    }

    public function testIsExpiredReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::EXPIRED->isExpired());
        $this->assertFalse(PaymentStatus::AUTHORIZED->isExpired());
    }

    public function testIsVoidedReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::VOIDED->isVoided());
        $this->assertFalse(PaymentStatus::AUTHORIZED->isVoided());
    }

    public function testIsDisputedReturnsTrue(): void
    {
        $this->assertTrue(PaymentStatus::DISPUTED->isDisputed());
        $this->assertFalse(PaymentStatus::CAPTURED->isDisputed());
    }

    // ==================== Value Method ====================

    public function testValueReturnsStringValue(): void
    {
        $this->assertSame('pending', PaymentStatus::PENDING->value());
        $this->assertSame('authorized', PaymentStatus::AUTHORIZED->value());
        $this->assertSame('captured', PaymentStatus::CAPTURED->value());
        $this->assertSame('partially_refunded', PaymentStatus::PARTIALLY_REFUNDED->value());
        $this->assertSame('refunded', PaymentStatus::REFUNDED->value());
        $this->assertSame('failed', PaymentStatus::FAILED->value());
        $this->assertSame('cancelled', PaymentStatus::CANCELLED->value());
        $this->assertSame('expired', PaymentStatus::EXPIRED->value());
        $this->assertSame('voided', PaymentStatus::VOIDED->value());
        $this->assertSame('disputed', PaymentStatus::DISPUTED->value());
    }
}
