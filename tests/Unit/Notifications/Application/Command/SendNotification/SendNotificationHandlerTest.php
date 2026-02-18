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
    public function testItCreatesAndSavesEmailNotification(): void
    {
        // Arrange
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notification) {
                return NotificationType::EMAIL === $notification->type()
                    && 'test@example.com' === $notification->recipientEmail()
                    && 'Test Subject' === $notification->subject()
                    && 'Test Body' === $notification->body();
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

    public function testItCreatesAndSavesSmsNotification(): void
    {
        // Arrange
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notification) {
                return NotificationType::SMS === $notification->type()
                    && '+40712345678' === $notification->recipientPhone()
                    && null === $notification->recipientEmail();
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

    public function testItUsesNotificationIdFromCommand(): void
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

    public function testItUsesTenantIdFromCommand(): void
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

    public function testItPropagatesValidationErrors(): void
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
