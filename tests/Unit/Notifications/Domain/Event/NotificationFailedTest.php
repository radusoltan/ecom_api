<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Domain\Event;

use App\Notifications\Domain\Event\NotificationFailed;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationFailed domain event.
 */
final class NotificationFailedTest extends TestCase
{
    public function testItCreatesEventWithAllProperties(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();
        $type = NotificationType::EMAIL;
        $recipientEmail = 'test@example.com';
        $reason = 'SMTP connection timeout';
        $attemptCount = 1;

        // Act
        $event = new NotificationFailed(
            notificationId: $notificationId,
            tenantId: $tenantId,
            type: $type,
            recipientEmail: $recipientEmail,
            reason: $reason,
            attemptCount: $attemptCount
        );

        // Assert
        $this->assertTrue($notificationId->equals($event->notificationId));
        $this->assertTrue($tenantId->equals($event->tenantId));
        $this->assertSame($type, $event->type);
        $this->assertSame($recipientEmail, $event->recipientEmail);
        $this->assertSame($reason, $event->reason);
        $this->assertSame($attemptCount, $event->attemptCount);
    }

    public function testItIsReadonly(): void
    {
        // Arrange
        $event = new NotificationFailed(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            reason: 'Error',
            attemptCount: 1
        );

        // Assert
        $reflection = new \ReflectionClass($event);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testItSupportsNullRecipientEmail(): void
    {
        // Arrange & Act
        $event = new NotificationFailed(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::WEBHOOK,
            recipientEmail: null,
            reason: 'Webhook endpoint unreachable',
            attemptCount: 1
        );

        // Assert
        $this->assertNull($event->recipientEmail);
    }

    public function testItTracksAttemptCount(): void
    {
        // Act
        $event1 = new NotificationFailed(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            reason: 'First attempt failed',
            attemptCount: 1
        );

        $event2 = new NotificationFailed(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            reason: 'Second attempt failed',
            attemptCount: 2
        );

        // Assert
        $this->assertSame(1, $event1->attemptCount);
        $this->assertSame(2, $event2->attemptCount);
    }

    public function testItPreservesFailureReason(): void
    {
        // Arrange
        $reason = 'SMTP server returned error 550: Mailbox not found';

        // Act
        $event = new NotificationFailed(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            reason: $reason,
            attemptCount: 1
        );

        // Assert
        $this->assertSame($reason, $event->reason);
    }
}
