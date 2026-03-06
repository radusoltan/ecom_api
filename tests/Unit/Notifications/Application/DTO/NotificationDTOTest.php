<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Application\DTO;

use App\Notifications\Application\DTO\NotificationDTO;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class NotificationDTOTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $dto = new NotificationDTO(
            id: 'notif-123',
            tenantId: 'tenant-456',
            type: 'email',
            recipientEmail: 'user@example.com',
            recipientPhone: null,
            subject: 'Order Confirmed',
            body: 'Your order has been confirmed.',
            status: 'pending',
            attemptCount: 0,
            sentAt: null,
            failedAt: null,
            failureReason: null,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
        );

        $this->assertSame('notif-123', $dto->id);
        $this->assertSame('tenant-456', $dto->tenantId);
        $this->assertSame('email', $dto->type);
        $this->assertSame('user@example.com', $dto->recipientEmail);
        $this->assertNull($dto->recipientPhone);
        $this->assertSame('Order Confirmed', $dto->subject);
        $this->assertSame('Your order has been confirmed.', $dto->body);
        $this->assertSame('pending', $dto->status);
        $this->assertSame(0, $dto->attemptCount);
        $this->assertNull($dto->sentAt);
        $this->assertNull($dto->failedAt);
        $this->assertNull($dto->failureReason);
    }

    public function testFromDomainModelMapsAllFields(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $notification = Notification::create(
            id: NotificationId::generate(),
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test body content',
        );

        $dto = NotificationDTO::fromDomainModel($notification);

        $this->assertSame($notification->id()->toString(), $dto->id);
        $this->assertSame($tenantId->toString(), $dto->tenantId);
        $this->assertSame('email', $dto->type);
        $this->assertSame('test@example.com', $dto->recipientEmail);
        $this->assertNull($dto->recipientPhone);
        $this->assertSame('Test Subject', $dto->subject);
        $this->assertSame('Test body content', $dto->body);
        $this->assertSame('pending', $dto->status);
        $this->assertSame(0, $dto->attemptCount);
        $this->assertNull($dto->sentAt);
        $this->assertNull($dto->failedAt);
        $this->assertNull($dto->failureReason);
    }
}
