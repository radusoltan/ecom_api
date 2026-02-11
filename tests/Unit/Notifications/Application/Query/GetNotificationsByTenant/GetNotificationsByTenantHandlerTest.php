<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Application\Query\GetNotificationsByTenant;

use App\Notifications\Application\DTO\NotificationDTO;
use App\Notifications\Application\Query\GetNotificationsByTenant\GetNotificationsByTenant;
use App\Notifications\Application\Query\GetNotificationsByTenant\GetNotificationsByTenantHandler;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GetNotificationsByTenantHandler.
 */
final class GetNotificationsByTenantHandlerTest extends TestCase
{
    public function test_it_returns_notifications_as_dtos(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        $notification1 = Notification::create(
            id: NotificationId::generate(),
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test1@example.com',
            recipientPhone: null,
            subject: 'Subject 1',
            body: 'Body 1'
        );

        $notification2 = Notification::create(
            id: NotificationId::generate(),
            tenantId: $tenantId,
            type: NotificationType::SMS,
            recipientEmail: null,
            recipientPhone: '+40712345678',
            subject: 'Subject 2',
            body: 'Body 2'
        );

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId)
            ->willReturn([$notification1, $notification2]);

        $handler = new GetNotificationsByTenantHandler($repository);

        $query = new GetNotificationsByTenant(tenantId: $tenantId);

        // Act
        $result = $handler($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnly(NotificationDTO::class, $result);

        // Verify first DTO
        $this->assertTrue($notification1->id()->equals(
            NotificationId::fromString($result[0]->id)
        ));
        $this->assertSame(NotificationType::EMAIL->value, $result[0]->type);
        $this->assertSame('test1@example.com', $result[0]->recipientEmail);

        // Verify second DTO
        $this->assertTrue($notification2->id()->equals(
            NotificationId::fromString($result[1]->id)
        ));
        $this->assertSame(NotificationType::SMS->value, $result[1]->type);
        $this->assertSame('+40712345678', $result[1]->recipientPhone);
    }

    public function test_it_returns_empty_array_when_no_notifications_found(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId)
            ->willReturn([]);

        $handler = new GetNotificationsByTenantHandler($repository);

        $query = new GetNotificationsByTenant(tenantId: $tenantId);

        // Act
        $result = $handler($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_it_uses_correct_tenant_id_for_query(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByTenant')
            ->with($this->callback(function (TenantId $id) use ($tenantId) {
                return $id->equals($tenantId);
            }))
            ->willReturn([]);

        $handler = new GetNotificationsByTenantHandler($repository);

        $query = new GetNotificationsByTenant(tenantId: $tenantId);

        // Act
        $handler($query);

        // Assert: Verified by mock expectations
    }

    public function test_it_converts_all_notifications_to_dtos(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        $notifications = [];
        for ($i = 1; $i <= 5; ++$i) {
            $notifications[] = Notification::create(
                id: NotificationId::generate(),
                tenantId: $tenantId,
                type: NotificationType::EMAIL,
                recipientEmail: "test{$i}@example.com",
                recipientPhone: null,
                subject: "Subject {$i}",
                body: "Body {$i}"
            );
        }

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId)
            ->willReturn($notifications);

        $handler = new GetNotificationsByTenantHandler($repository);

        $query = new GetNotificationsByTenant(tenantId: $tenantId);

        // Act
        $result = $handler($query);

        // Assert
        $this->assertCount(5, $result);
        $this->assertContainsOnly(NotificationDTO::class, $result);

        foreach ($result as $index => $dto) {
            $this->assertInstanceOf(NotificationDTO::class, $dto);
            $this->assertSame("test" . ($index + 1) . "@example.com", $dto->recipientEmail);
        }
    }
}
