<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Domain\Model;

use App\Notifications\Domain\Model\NotificationStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationStatus enum.
 *
 * Tests notification lifecycle states and transitions.
 */
final class NotificationStatusTest extends TestCase
{
    public function testItHasPendingCase(): void
    {
        // Act
        $status = NotificationStatus::PENDING;

        // Assert
        $this->assertSame('pending', $status->value);
        $this->assertTrue($status->isPending());
        $this->assertFalse($status->isSent());
        $this->assertFalse($status->isFailed());
        $this->assertFalse($status->isCancelled());
    }

    public function testItHasSentCase(): void
    {
        // Act
        $status = NotificationStatus::SENT;

        // Assert
        $this->assertSame('sent', $status->value);
        $this->assertTrue($status->isSent());
        $this->assertFalse($status->isPending());
        $this->assertFalse($status->isFailed());
        $this->assertFalse($status->isCancelled());
    }

    public function testItHasFailedCase(): void
    {
        // Act
        $status = NotificationStatus::FAILED;

        // Assert
        $this->assertSame('failed', $status->value);
        $this->assertTrue($status->isFailed());
        $this->assertFalse($status->isPending());
        $this->assertFalse($status->isSent());
        $this->assertFalse($status->isCancelled());
    }

    public function testItHasCancelledCase(): void
    {
        // Act
        $status = NotificationStatus::CANCELLED;

        // Assert
        $this->assertSame('cancelled', $status->value);
        $this->assertTrue($status->isCancelled());
        $this->assertFalse($status->isPending());
        $this->assertFalse($status->isSent());
        $this->assertFalse($status->isFailed());
    }

    public function testSentIsTerminalState(): void
    {
        // Arrange
        $status = NotificationStatus::SENT;

        // Assert
        $this->assertTrue($status->isTerminal());
    }

    public function testCancelledIsTerminalState(): void
    {
        // Arrange
        $status = NotificationStatus::CANCELLED;

        // Assert
        $this->assertTrue($status->isTerminal());
    }

    public function testPendingIsNotTerminalState(): void
    {
        // Arrange
        $status = NotificationStatus::PENDING;

        // Assert
        $this->assertFalse($status->isTerminal());
    }

    public function testFailedIsNotTerminalState(): void
    {
        // Arrange
        $status = NotificationStatus::FAILED;

        // Assert
        $this->assertFalse($status->isTerminal());
    }

    public function testFailedCanRetry(): void
    {
        // Arrange
        $status = NotificationStatus::FAILED;

        // Assert
        $this->assertTrue($status->canRetry());
    }

    public function testPendingCannotRetry(): void
    {
        // Arrange
        $status = NotificationStatus::PENDING;

        // Assert
        $this->assertFalse($status->canRetry());
    }

    public function testSentCannotRetry(): void
    {
        // Arrange
        $status = NotificationStatus::SENT;

        // Assert
        $this->assertFalse($status->canRetry());
    }

    public function testCancelledCannotRetry(): void
    {
        // Arrange
        $status = NotificationStatus::CANCELLED;

        // Assert
        $this->assertFalse($status->canRetry());
    }

    public function testItSupportsAllCases(): void
    {
        // Arrange & Act
        $cases = NotificationStatus::cases();

        // Assert
        $this->assertCount(4, $cases);
        $this->assertContains(NotificationStatus::PENDING, $cases);
        $this->assertContains(NotificationStatus::SENT, $cases);
        $this->assertContains(NotificationStatus::FAILED, $cases);
        $this->assertContains(NotificationStatus::CANCELLED, $cases);
    }

    public function testItCreatesFromStringValue(): void
    {
        // Act
        $pending = NotificationStatus::from('pending');
        $sent = NotificationStatus::from('sent');
        $failed = NotificationStatus::from('failed');
        $cancelled = NotificationStatus::from('cancelled');

        // Assert
        $this->assertSame(NotificationStatus::PENDING, $pending);
        $this->assertSame(NotificationStatus::SENT, $sent);
        $this->assertSame(NotificationStatus::FAILED, $failed);
        $this->assertSame(NotificationStatus::CANCELLED, $cancelled);
    }
}
