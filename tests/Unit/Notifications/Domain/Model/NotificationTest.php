<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Domain\Model;

use App\Notifications\Domain\Event\NotificationCreated;
use App\Notifications\Domain\Event\NotificationFailed;
use App\Notifications\Domain\Event\NotificationSent;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationStatus;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Notification aggregate root.
 *
 * Tests all business rules and state transitions.
 */
final class NotificationTest extends TestCase
{
    // ========== Creation Tests ==========

    public function test_it_creates_email_notification(): void
    {
        // Arrange
        $id = NotificationId::generate();
        $tenantId = TenantId::generate();

        // Act
        $notification = Notification::create(
            id: $id,
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        // Assert
        $this->assertTrue($id->equals($notification->id()));
        $this->assertTrue($tenantId->equals($notification->tenantId()));
        $this->assertSame(NotificationType::EMAIL, $notification->type());
        $this->assertSame('test@example.com', $notification->recipientEmail());
        $this->assertNull($notification->recipientPhone());
        $this->assertSame('Test Subject', $notification->subject());
        $this->assertSame('Test Body', $notification->body());
        $this->assertSame(NotificationStatus::PENDING, $notification->status());
        $this->assertSame(0, $notification->attemptCount());
        $this->assertNull($notification->sentAt());
        $this->assertNull($notification->failedAt());
        $this->assertNull($notification->failureReason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->createdAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->updatedAt());
    }

    public function test_it_creates_sms_notification(): void
    {
        // Arrange
        $id = NotificationId::generate();
        $tenantId = TenantId::generate();

        // Act
        $notification = Notification::create(
            id: $id,
            tenantId: $tenantId,
            type: NotificationType::SMS,
            recipientEmail: null,
            recipientPhone: '+40712345678',
            subject: 'SMS Subject',
            body: 'SMS Body'
        );

        // Assert
        $this->assertSame(NotificationType::SMS, $notification->type());
        $this->assertNull($notification->recipientEmail());
        $this->assertSame('+40712345678', $notification->recipientPhone());
        $this->assertSame(NotificationStatus::PENDING, $notification->status());
    }

    public function test_it_records_notification_created_event(): void
    {
        // Arrange
        $id = NotificationId::generate();
        $tenantId = TenantId::generate();

        // Act
        $notification = Notification::create(
            id: $id,
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        // Assert
        $events = $notification->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(NotificationCreated::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($id->equals($event->notificationId));
        $this->assertTrue($tenantId->equals($event->tenantId));
        $this->assertSame(NotificationType::EMAIL, $event->type);
        $this->assertSame('test@example.com', $event->recipientEmail);
        $this->assertSame('Test Subject', $event->subject);
    }

    // ========== Validation Tests ==========

    public function test_it_throws_when_email_notification_has_no_recipient_email(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email notifications require a recipient email address');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: null,
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );
    }

    public function test_it_throws_when_email_notification_has_empty_recipient_email(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email notifications require a recipient email address');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: '',
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );
    }

    public function test_it_throws_when_email_is_invalid(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid recipient email');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'invalid-email',
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );
    }

    public function test_it_throws_when_sms_notification_has_no_recipient_phone(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS notifications require a recipient phone number');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::SMS,
            recipientEmail: null,
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );
    }

    public function test_it_throws_when_sms_notification_has_empty_recipient_phone(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS notifications require a recipient phone number');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::SMS,
            recipientEmail: null,
            recipientPhone: '',
            subject: 'Test',
            body: 'Test'
        );
    }

    public function test_it_throws_when_subject_is_empty(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification subject cannot be empty');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: '',
            body: 'Test Body'
        );
    }

    public function test_it_throws_when_subject_is_only_whitespace(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification subject cannot be empty');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: '   ',
            body: 'Test Body'
        );
    }

    public function test_it_throws_when_body_is_empty(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification body cannot be empty');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: ''
        );
    }

    public function test_it_throws_when_body_is_only_whitespace(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification body cannot be empty');

        // Act
        Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: '   '
        );
    }

    // ========== Mark as Sent Tests ==========

    public function test_it_marks_as_sent(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();

        // Act
        $notification->markAsSent();

        // Assert
        $this->assertSame(NotificationStatus::SENT, $notification->status());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->sentAt());
        $this->assertNull($notification->failedAt());
        $this->assertNull($notification->failureReason());
    }

    public function test_it_records_notification_sent_event(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->pullDomainEvents(); // Clear creation event

        // Act
        $notification->markAsSent();

        // Assert
        $events = $notification->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(NotificationSent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($notification->id()->equals($event->notificationId));
        $this->assertSame(NotificationType::EMAIL, $event->type);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->sentAt);
    }

    public function test_it_throws_when_marking_sent_notification_as_sent_again(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->markAsSent();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot mark notification as sent. Current status: sent');

        // Act
        $notification->markAsSent();
    }

    public function test_it_throws_when_marking_cancelled_notification_as_sent(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->cancel();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot mark notification as sent. Current status: cancelled');

        // Act
        $notification->markAsSent();
    }

    // ========== Mark as Failed Tests ==========

    public function test_it_marks_as_failed(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();

        // Act
        $notification->markAsFailed('SMTP connection failed');

        // Assert
        $this->assertSame(NotificationStatus::FAILED, $notification->status());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->failedAt());
        $this->assertSame('SMTP connection failed', $notification->failureReason());
        $this->assertSame(1, $notification->attemptCount());
        $this->assertNull($notification->sentAt());
    }

    public function test_it_increments_attempt_count_on_failure(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();

        // Act
        $notification->markAsFailed('Error 1');
        $this->assertSame(1, $notification->attemptCount());

        $notification->retry();
        $notification->markAsFailed('Error 2');
        $this->assertSame(2, $notification->attemptCount());

        $notification->retry();
        $notification->markAsFailed('Error 3');

        // Assert
        $this->assertSame(3, $notification->attemptCount());
    }

    public function test_it_records_notification_failed_event(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->pullDomainEvents(); // Clear creation event

        // Act
        $notification->markAsFailed('Test error');

        // Assert
        $events = $notification->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(NotificationFailed::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($notification->id()->equals($event->notificationId));
        $this->assertSame('Test error', $event->reason);
        $this->assertSame(1, $event->attemptCount);
    }

    public function test_it_throws_when_marking_failed_with_empty_reason(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failure reason cannot be empty');

        // Act
        $notification->markAsFailed('');
    }

    public function test_it_throws_when_marking_sent_notification_as_failed(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->markAsSent();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot mark notification as failed. Current status: sent');

        // Act
        $notification->markAsFailed('Error');
    }

    // ========== Retry Tests ==========

    public function test_it_retries_failed_notification(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->markAsFailed('Initial error');

        // Act
        $notification->retry();

        // Assert
        $this->assertSame(NotificationStatus::PENDING, $notification->status());
        $this->assertNull($notification->failedAt());
        $this->assertNull($notification->failureReason());
        $this->assertSame(1, $notification->attemptCount()); // Attempt count persists
    }

    public function test_it_throws_when_retrying_pending_notification(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot retry notification. Current status: pending');

        // Act
        $notification->retry();
    }

    public function test_it_throws_when_retrying_sent_notification(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->markAsSent();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot retry notification. Current status: sent');

        // Act
        $notification->retry();
    }

    public function test_it_throws_when_max_retry_attempts_reached(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();

        // Fail 3 times (max)
        $notification->markAsFailed('Error 1');
        $notification->retry();
        $notification->markAsFailed('Error 2');
        $notification->retry();
        $notification->markAsFailed('Error 3');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Maximum retry attempts (3) reached');

        // Act
        $notification->retry();
    }

    // ========== Cancel Tests ==========

    public function test_it_cancels_pending_notification(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();

        // Act
        $notification->cancel();

        // Assert
        $this->assertSame(NotificationStatus::CANCELLED, $notification->status());
    }

    public function test_it_cancels_failed_notification(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->markAsFailed('Error');

        // Act
        $notification->cancel();

        // Assert
        $this->assertSame(NotificationStatus::CANCELLED, $notification->status());
    }

    public function test_it_throws_when_cancelling_sent_notification(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->markAsSent();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel notification. Current status: sent');

        // Act
        $notification->cancel();
    }

    public function test_it_throws_when_cancelling_already_cancelled_notification(): void
    {
        // Arrange
        $notification = $this->createPendingNotification();
        $notification->cancel();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel notification. Current status: cancelled');

        // Act
        $notification->cancel();
    }

    // ========== Reconstitution Tests ==========

    public function test_it_reconstitutes_from_persistence(): void
    {
        // Arrange
        $id = NotificationId::generate();
        $tenantId = TenantId::generate();
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-01-01 10:05:00');
        $sentAt = new \DateTimeImmutable('2025-01-01 10:05:00');

        // Act
        $notification = Notification::reconstituteFromPersistence(
            id: $id,
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body',
            status: NotificationStatus::SENT,
            attemptCount: 1,
            sentAt: $sentAt,
            failedAt: null,
            failureReason: null,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        // Assert
        $this->assertTrue($id->equals($notification->id()));
        $this->assertTrue($tenantId->equals($notification->tenantId()));
        $this->assertSame(NotificationStatus::SENT, $notification->status());
        $this->assertSame(1, $notification->attemptCount());
        $this->assertSame($sentAt, $notification->sentAt());
        $this->assertSame($createdAt, $notification->createdAt());
        $this->assertSame($updatedAt, $notification->updatedAt());
    }

    // ========== Helper Methods ==========

    private function createPendingNotification(): Notification
    {
        return Notification::create(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body'
        );
    }
}
