<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notifications\Api;

use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class NotificationApiTest extends WebTestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private TenantId $tenantId;
    private TenantId $otherTenantId;
    private static ?EntityManagerInterface $entityManager = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $client = static::createClient();
        self::$entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Use default test tenant
        $this->tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $this->otherTenantId = TenantId::generate();

        $this->cleanupNotifications();
    }

    protected function tearDown(): void
    {
        $this->cleanupNotifications();
        parent::tearDown();
    }

    private function cleanupNotifications(): void
    {
        if (null === self::$entityManager) {
            return;
        }

        $connection = self::$entityManager->getConnection();

        // Set tenant context for RLS
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $this->tenantId->toString())
        );

        try {
            $connection->executeStatement(
                'DELETE FROM notifications WHERE tenant_id = :tenant_id',
                ['tenant_id' => $this->tenantId->toString()]
            );
        } catch (\Exception $e) {
            // Table might not exist - ignore
        }
    }

    public function testGetNotificationsCollection(): void
    {
        $client = static::createClient();

        // Create test notification
        $notificationId = $this->createTestNotification();

        $client->request('GET', '/api/v1/notifications', [], [], [
            'HTTP_X_TENANT_ID' => $this->tenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertCount(1, $data);
        $this->assertSame($notificationId, $data[0]['id']);
    }

    public function testGetNotificationsCollectionRequiresAuth(): void
    {
        $client = static::createClient();

        // Without tenant header should fail
        $client->request('GET', '/api/v1/notifications', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Should fail due to missing tenant context
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testGetSingleNotification(): void
    {
        $client = static::createClient();

        $notificationId = $this->createTestNotification();

        $client->request('GET', '/api/v1/notifications/'.$notificationId, [], [], [
            'HTTP_X_TENANT_ID' => $this->tenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($notificationId, $data['id']);
        $this->assertSame('email', $data['type']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('test@example.com', $data['recipientEmail']);
        $this->assertSame('Test Subject', $data['subject']);
    }

    public function testGetNotificationNotFound(): void
    {
        $client = static::createClient();

        $nonExistentId = NotificationId::generate()->toString();

        $client->request('GET', '/api/v1/notifications/'.$nonExistentId, [], [], [
            'HTTP_X_TENANT_ID' => $this->tenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRetryFailedNotification(): void
    {
        $client = static::createClient();

        $notificationId = $this->createFailedNotification();

        $client->request('POST', '/api/v1/notifications/'.$notificationId.'/retry', [], [], [
            'HTTP_X_TENANT_ID' => $this->tenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('retry_queued', $data['status']);
    }

    public function testRetryNonFailedNotificationFails(): void
    {
        $client = static::createClient();

        // Create a notification with status 'pending' (cannot retry pending notifications)
        $notificationId = $this->createTestNotification();

        $client->request('POST', '/api/v1/notifications/'.$notificationId.'/retry', [], [], [
            'HTTP_X_TENANT_ID' => $this->tenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Notification cannot be retried', $data['hydra:description']);
    }

    public function testRetrySentNotificationFails(): void
    {
        $client = static::createClient();

        // Create a sent notification (cannot retry sent notifications)
        $notificationId = $this->createSentNotification();

        $client->request('POST', '/api/v1/notifications/'.$notificationId.'/retry', [], [], [
            'HTTP_X_TENANT_ID' => $this->tenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testTenantIsolation(): void
    {
        $client = static::createClient();

        // Create notification for default tenant
        $notificationId = $this->createTestNotification();

        // Try to access with different tenant ID
        $client->request('GET', '/api/v1/notifications/'.$notificationId, [], [], [
            'HTTP_X_TENANT_ID' => $this->otherTenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Should return 404 because of tenant isolation
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCollectionPagination(): void
    {
        $client = static::createClient();

        // Create multiple notifications
        for ($i = 0; $i < 35; ++$i) {
            $this->createTestNotification();
        }

        $client->request('GET', '/api/v1/notifications', [], [], [
            'HTTP_X_TENANT_ID' => $this->tenantId->toString(),
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        // Default page size is 30
        $this->assertCount(30, $data);
    }

    private function createTestNotification(): string
    {
        $connection = self::$entityManager->getConnection();

        // Set tenant context for RLS
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $this->tenantId->toString())
        );

        $notification = Notification::create(
            id: NotificationId::generate(),
            tenantId: $this->tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        $entity = \App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity::fromDomainModel($notification);

        self::$entityManager->persist($entity);
        self::$entityManager->flush();

        return $notification->id()->toString();
    }

    private function createFailedNotification(): string
    {
        $connection = self::$entityManager->getConnection();

        // Set tenant context for RLS
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $this->tenantId->toString())
        );

        $notification = Notification::create(
            id: NotificationId::generate(),
            tenantId: $this->tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        // Mark as failed so it can be retried
        $notification->markAsFailed('Test failure reason');

        $entity = \App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity::fromDomainModel($notification);

        self::$entityManager->persist($entity);
        self::$entityManager->flush();

        return $notification->id()->toString();
    }

    private function createSentNotification(): string
    {
        $connection = self::$entityManager->getConnection();

        // Set tenant context for RLS
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $this->tenantId->toString())
        );

        $notification = Notification::create(
            id: NotificationId::generate(),
            tenantId: $this->tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'test@example.com',
            recipientPhone: null,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        // Mark as sent
        $notification->markAsSent();

        $entity = \App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity::fromDomainModel($notification);

        self::$entityManager->persist($entity);
        self::$entityManager->flush();

        return $notification->id()->toString();
    }
}
