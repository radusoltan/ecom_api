<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Domain\Event;

use App\Notifications\Domain\Event\NotificationSent;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationSent domain event.
 */
final class NotificationSentTest extends TestCase
{
    public function testItCreatesEventWithAllProperties(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();
        $type = NotificationType::EMAIL;
        $recipientEmail = 'test@example.com';
        $sentAt = new \DateTimeImmutable();

        // Act
        $event = new NotificationSent(
            notificationId: $notificationId,
            tenantId: $tenantId,
            type: $type,
            recipientEmail: $recipientEmail,
            sentAt: $sentAt
        );

        // Assert
        $this->assertTrue($notificationId->equals($event->notificationId));
        $this->assertTrue($tenantId->equals($event->tenantId));
        $this->assertSame($type, $event->type);
        $this->assertSame($recipientEmail, $event->recipientEmail);
        $this->assertSame($sentAt, $event->sentAt);
    }

    public function testItIsReadonly(): void
    {
        // Arrange
        $event = new NotificationSent(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            sentAt: new \DateTimeImmutable()
        );

        // Assert
        $reflection = new \ReflectionClass($event);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testItSupportsNullRecipientEmail(): void
    {
        // Arrange & Act
        $event = new NotificationSent(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::WEBHOOK,
            recipientEmail: null,
            sentAt: new \DateTimeImmutable()
        );

        // Assert
        $this->assertNull($event->recipientEmail);
    }

    public function testItPreservesSentTimestamp(): void
    {
        // Arrange
        $sentAt = new \DateTimeImmutable('2025-01-15 10:30:00');

        // Act
        $event = new NotificationSent(
            notificationId: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            sentAt: $sentAt
        );

        // Assert
        $this->assertSame($sentAt, $event->sentAt);
        $this->assertSame('2025-01-15 10:30:00', $event->sentAt->format('Y-m-d H:i:s'));
    }
}
