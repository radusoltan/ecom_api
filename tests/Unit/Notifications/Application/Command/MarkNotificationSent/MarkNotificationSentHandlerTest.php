<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Application\Command\MarkNotificationSent;

use App\Notifications\Application\Command\MarkNotificationSent\MarkNotificationSent;
use App\Notifications\Application\Command\MarkNotificationSent\MarkNotificationSentHandler;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationStatus;
use App\Notifications\Domain\Model\NotificationType;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MarkNotificationSentHandler.
 */
final class MarkNotificationSentHandlerTest extends TestCase
{
    public function testItMarksNotificationAsSentAndSaves(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();

        $notification = Notification::create(
            id: $notificationId,
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($notificationId, $tenantId)
            ->willReturn($notification);

        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $savedNotification) {
                return NotificationStatus::SENT === $savedNotification->status()
                    && null !== $savedNotification->sentAt();
            }));

        $handler = new MarkNotificationSentHandler($repository);

        $command = new MarkNotificationSent(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Act
        $handler($command);

        // Assert: Verified by mock expectations
    }

    public function testItThrowsWhenNotificationNotFound(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($notificationId, $tenantId)
            ->willReturn(null);

        $repository->expects($this->never())->method('save');

        $handler = new MarkNotificationSentHandler($repository);

        $command = new MarkNotificationSent(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found for tenant');

        // Act
        $handler($command);
    }

    public function testItPropagatesDomainExceptions(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();

        $notification = Notification::create(
            id: $notificationId,
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test',
            body: 'Test'
        );

        // Already sent
        $notification->markAsSent();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->willReturn($notification);

        $repository->expects($this->never())->method('save');

        $handler = new MarkNotificationSentHandler($repository);

        $command = new MarkNotificationSent(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot mark notification as sent');

        // Act
        $handler($command);
    }

    public function testItUsesCorrectTenantIdForQuery(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($notificationId, $tenantId)
            ->willReturn(null);

        $handler = new MarkNotificationSentHandler($repository);

        $command = new MarkNotificationSent(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Assert
        $this->expectException(\DomainException::class);

        // Act
        $handler($command);
    }
}
