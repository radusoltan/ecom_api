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
    public function test_it_creates_event_with_all_properties(): void
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

    public function test_it_is_readonly(): void
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

    public function test_it_supports_null_recipient_email(): void
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

    public function test_it_tracks_attempt_count(): void
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

    public function test_it_preserves_failure_reason(): void
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
