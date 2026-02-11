<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Domain\Event;

use App\Notifications\Domain\Event\NotificationCreated;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationCreated domain event.
 */
final class NotificationCreatedTest extends TestCase
{
    public function test_it_creates_event_with_all_properties(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();
        $type = NotificationType::EMAIL;
        $recipientEmail = 'test@example.com';
        $subject = 'Test Subject';

        // Act
        $event = new NotificationCreated(
            notificationId: $notificationId,
            tenantId: $tenantId,
            type: $type,
            recipientEmail: $recipientEmail,
            subject: $subject
        );

        // Assert
        $this->assertTrue($notificationId->equals($event->notificationId));
        $this->assertTrue($tenantId->equals($event->tenantId));
        $this->assertSame($type, $event->type);
        $this->assertSame($recipientEmail, $event->recipientEmail);
        $this->assertSame($subject, $event->subject);
    }

    public function test_it_is_readonly(): void
    {
        // Arrange
        $event = new NotificationCreated(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            subject: 'Test'
        );

        // Assert
        $reflection = new \ReflectionClass($event);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_it_supports_null_recipient_email(): void
    {
        // Arrange & Act
        $event = new NotificationCreated(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::WEBHOOK,
            recipientEmail: null,
            subject: 'Test'
        );

        // Assert
        $this->assertNull($event->recipientEmail);
    }
}
