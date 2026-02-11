<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Application\Command\RetryNotification;

use App\Notifications\Application\Command\RetryNotification\RetryNotification;
use App\Notifications\Application\Command\RetryNotification\RetryNotificationHandler;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationStatus;
use App\Notifications\Domain\Model\NotificationType;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RetryNotificationHandler.
 */
final class RetryNotificationHandlerTest extends TestCase
{
    public function test_it_retries_failed_notification_and_saves(): void
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

        // Make it failed first
        $notification->markAsFailed('Initial error');

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($notificationId, $tenantId)
            ->willReturn($notification);

        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $savedNotification) {
                return $savedNotification->status() === NotificationStatus::PENDING
                    && $savedNotification->failedAt() === null
                    && $savedNotification->failureReason() === null
                    && $savedNotification->attemptCount() === 1; // Persists from failure
            }));

        $handler = new RetryNotificationHandler($repository);

        $command = new RetryNotification(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Act
        $handler($command);

        // Assert: Verified by mock expectations
    }

    public function test_it_throws_when_notification_not_found(): void
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

        $handler = new RetryNotificationHandler($repository);

        $command = new RetryNotification(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found for tenant');

        // Act
        $handler($command);
    }

    public function test_it_throws_when_notification_is_not_failed(): void
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

        // Still pending, not failed

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->willReturn($notification);

        $repository->expects($this->never())->method('save');

        $handler = new RetryNotificationHandler($repository);

        $command = new RetryNotification(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot retry notification');

        // Act
        $handler($command);
    }

    public function test_it_throws_when_max_retries_exceeded(): void
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

        // Fail 3 times (max)
        $notification->markAsFailed('Error 1');
        $notification->retry();
        $notification->markAsFailed('Error 2');
        $notification->retry();
        $notification->markAsFailed('Error 3');

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->willReturn($notification);

        $repository->expects($this->never())->method('save');

        $handler = new RetryNotificationHandler($repository);

        $command = new RetryNotification(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Maximum retry attempts');

        // Act
        $handler($command);
    }

    public function test_it_uses_correct_tenant_id_for_query(): void
    {
        // Arrange
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::generate();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($notificationId, $tenantId)
            ->willReturn(null);

        $handler = new RetryNotificationHandler($repository);

        $command = new RetryNotification(
            id: $notificationId,
            tenantId: $tenantId
        );

        // Assert
        $this->expectException(\DomainException::class);

        // Act
        $handler($command);
    }
}
