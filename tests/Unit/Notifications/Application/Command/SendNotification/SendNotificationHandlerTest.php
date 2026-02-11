<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Application\Command\SendNotification;

use App\Notifications\Application\Command\SendNotification\SendNotification;
use App\Notifications\Application\Command\SendNotification\SendNotificationHandler;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SendNotificationHandler.
 */
final class SendNotificationHandlerTest extends TestCase
{
    public function test_it_creates_and_saves_email_notification(): void
    {
        // Arrange
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notification) {
                return $notification->type() === NotificationType::EMAIL
                    && $notification->recipientEmail() === 'test@example.com'
                    && $notification->subject() === 'Test Subject'
                    && $notification->body() === 'Test Body';
            }));

        $handler = new SendNotificationHandler($repository);

        $command = new SendNotification(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        // Act
        $handler($command);

        // Assert: Verified by mock expectations
    }

    public function test_it_creates_and_saves_sms_notification(): void
    {
        // Arrange
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notification) {
                return $notification->type() === NotificationType::SMS
                    && $notification->recipientPhone() === '+40712345678'
                    && $notification->recipientEmail() === null;
            }));

        $handler = new SendNotificationHandler($repository);

        $command = new SendNotification(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::SMS,
            recipientEmail: null,
            recipientPhone: '+40712345678',
            subject: 'SMS Subject',
            body: 'SMS Body'
        );

        // Act
        $handler($command);

        // Assert: Verified by mock expectations
    }

    public function test_it_uses_notification_id_from_command(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notification) use ($notificationId) {
                return $notification->id()->equals($notificationId);
            }));

        $handler = new SendNotificationHandler($repository);

        $command = new SendNotification(
            id: $notificationId,
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );

        // Act
        $handler($command);

        // Assert: Verified by mock expectations
    }

    public function test_it_uses_tenant_id_from_command(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notification) use ($tenantId) {
                return $notification->tenantId()->equals($tenantId);
            }));

        $handler = new SendNotificationHandler($repository);

        $command = new SendNotification(
            id: NotificationId::generate(),
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );

        // Act
        $handler($command);

        // Assert: Verified by mock expectations
    }

    public function test_it_propagates_validation_errors(): void
    {
        // Arrange
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->never())->method('save');

        $handler = new SendNotificationHandler($repository);

        $command = new SendNotification(
            id: NotificationId::generate(),
            tenantId: TenantId::generate(),
            type: NotificationType::EMAIL,
            recipientEmail: null, // Invalid: email required
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $handler($command);
    }
}
