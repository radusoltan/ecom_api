<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Infrastructure\Persistence\Doctrine\Entity;

use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class NotificationEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $notification = Notification::create(
            NotificationId::generate(),
            TenantId::generate(),
            NotificationType::EMAIL,
            'user@example.com',
            null,
            'Order Confirmed',
            'Your order has been confirmed.',
        );

        $entity = NotificationEntity::fromDomainModel($notification);

        self::assertSame($notification->id()->toString(), $entity->getId());
        self::assertSame('email', $entity->getType());
        self::assertSame('user@example.com', $entity->getRecipientEmail());
        self::assertNull($entity->getRecipientPhone());
        self::assertSame('Order Confirmed', $entity->getSubject());
        self::assertSame('Your order has been confirmed.', $entity->getBody());
        self::assertSame('pending', $entity->getStatus());
        self::assertSame(0, $entity->getAttemptCount());
        self::assertNull($entity->getSentAt());
        self::assertNull($entity->getFailedAt());
        self::assertNull($entity->getFailureReason());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($notification->id()));
        self::assertSame('Order Confirmed', $restored->subject());
    }

    public function testUpdateFromDomainModel(): void
    {
        $notification = Notification::create(
            NotificationId::generate(),
            TenantId::generate(),
            NotificationType::EMAIL,
            'test@example.com',
            null,
            'Test Subject',
            'Test body',
        );

        $entity = NotificationEntity::fromDomainModel($notification);
        $notification->markAsSent();
        $entity->updateFromDomainModel($notification);

        self::assertSame('sent', $entity->getStatus());
    }
}
