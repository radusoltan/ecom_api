<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notifications\Infrastructure\Persistence\Doctrine\Repository;

use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineNotificationRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private NotificationRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->repository = $container->get(NotificationRepositoryInterface::class);

        $this->cleanupNotificationData();
    }

    protected function tearDown(): void
    {
        $this->cleanupNotificationData();
        parent::tearDown();
    }

    private function cleanupNotificationData(): void
    {
        $em = $this->getEntityManager();
        $em->getConnection()->executeStatement(
            'DELETE FROM notifications WHERE tenant_id = :tid',
            ['tid' => $this->tenantId->toString()]
        );
    }

    private function createEmailNotification(): Notification
    {
        return Notification::create(
            id: NotificationId::generate(),
            tenantId: $this->tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Notification',
            body: 'This is a test notification body.',
        );
    }

    public function testSaveAndFindById(): void
    {
        $notification = $this->createEmailNotification();

        $this->repository->save($notification);

        $found = $this->repository->findById($notification->id(), $this->tenantId);

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($notification->id()));
        self::assertTrue($found->tenantId()->equals($this->tenantId));
        self::assertSame('test@example.com', $found->recipientEmail());
        self::assertSame('Test Notification', $found->subject());
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $nonExistentId = NotificationId::generate();

        $result = $this->repository->findById($nonExistentId, $this->tenantId);

        self::assertNull($result);
    }

    public function testFindByTenant(): void
    {
        $notification1 = $this->createEmailNotification();
        $notification2 = Notification::create(
            id: NotificationId::generate(),
            tenantId: $this->tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'another@example.com',
            recipientPhone: null,
            subject: 'Second Notification',
            body: 'Second notification body.',
        );

        $this->repository->save($notification1);
        $this->repository->save($notification2);

        $notifications = $this->repository->findByTenant($this->tenantId);

        self::assertGreaterThanOrEqual(2, count($notifications));
    }

    public function testFindPending(): void
    {
        $notification = $this->createEmailNotification();
        $this->repository->save($notification);

        $pending = $this->repository->findPending($this->tenantId);

        self::assertNotEmpty($pending);
        foreach ($pending as $n) {
            self::assertSame('pending', $n->status()->value);
        }
    }

    public function testFindFailed(): void
    {
        $notification = $this->createEmailNotification();
        $notification->markAsFailed('SMTP connection refused');
        $this->repository->save($notification);

        $failed = $this->repository->findFailed($this->tenantId);

        self::assertNotEmpty($failed);
    }
}
