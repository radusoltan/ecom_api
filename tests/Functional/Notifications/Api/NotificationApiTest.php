<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notifications\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class NotificationApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected static ?bool $alwaysBootKernel = false;

    private TenantId $tenantId;
    private TenantId $otherTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        static::createClient();

        $this->tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $this->otherTenantId = TenantId::generate();

        $this->setTenantContext($this->tenantId->toString());

        $this->cleanupNotifications();
    }

    protected function tearDown(): void
    {
        $this->cleanupNotifications();

        parent::tearDown();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    /**
     * @param list<string> $roles
     */
    protected function createAuthenticatedClient(
        string $email = 'admin@test.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
        ?string $tenantId = null,
    ): \ApiPlatform\Symfony\Bundle\Test\Client {
        $container = static::getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(UserEntity::class);
        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new UserEntity();
            $userEntity->setId(Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0].'-'.bin2hex(random_bytes(4)));
            $userEntity->setPassword('$2y$13$dummy.password.hash');
            $userEntity->setRoles($roles);
            $userEntity->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($userEntity);
            $entityManager->flush();
        }

        $encoder = $container->get('lexik_jwt_authentication.encoder');
        $token = $encoder->encode([
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $headers = [
            'authorization' => 'Bearer '.$token,
        ];

        $tenantIdToUse = $tenantId ?? $this->tenantId->toString();
        $headers['x-tenant-id'] = $tenantIdToUse;

        return static::createClient([], ['headers' => $headers]);
    }

    private function cleanupNotifications(): void
    {
        $connection = $this->getEntityManager()->getConnection();

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
        $client = $this->createAuthenticatedClient();

        $notificationId = $this->createTestNotification();

        $response = $client->request('GET', '/api/v1/notifications', [
            'headers' => [
                'x-tenant-id' => $this->tenantId->toString(),
                'accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = $response->toArray();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertCount(1, $data);
        $this->assertSame($notificationId, $data[0]['id']);
    }

    public function testGetNotificationsCollectionRequiresAuth(): void
    {
        static::createClient()->request('GET', '/api/v1/notifications', [
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetSingleNotification(): void
    {
        $client = $this->createAuthenticatedClient();

        $notificationId = $this->createTestNotification();

        $response = $client->request('GET', '/api/v1/notifications/'.$notificationId, [
            'headers' => [
                'x-tenant-id' => $this->tenantId->toString(),
                'accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertSame($notificationId, $data['id']);
        $this->assertSame('email', $data['type']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('test@example.com', $data['recipientEmail']);
        $this->assertSame('Test Subject', $data['subject']);
    }

    public function testGetNotificationNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $nonExistentId = NotificationId::generate()->toString();

        $client->request('GET', '/api/v1/notifications/'.$nonExistentId, [
            'headers' => [
                'x-tenant-id' => $this->tenantId->toString(),
                'accept' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRetryFailedNotification(): void
    {
        $client = $this->createAuthenticatedClient();

        $notificationId = $this->createFailedNotification();

        $response = $client->request('POST', '/api/v1/notifications/'.$notificationId.'/retry', [
            'headers' => [
                'x-tenant-id' => $this->tenantId->toString(),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $data = $response->toArray();
        $this->assertSame('retry_queued', $data['status']);
    }

    public function testRetryNonFailedNotificationFails(): void
    {
        $client = $this->createAuthenticatedClient();

        $notificationId = $this->createTestNotification();

        $response = $client->request('POST', '/api/v1/notifications/'.$notificationId.'/retry', [
            'headers' => [
                'x-tenant-id' => $this->tenantId->toString(),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = $response->toArray(false);
        $this->assertStringContainsString('Notification cannot be retried', $data['detail'] ?? $data['hydra:description'] ?? '');
    }

    public function testRetrySentNotificationFails(): void
    {
        $client = $this->createAuthenticatedClient();

        $notificationId = $this->createSentNotification();

        $client->request('POST', '/api/v1/notifications/'.$notificationId.'/retry', [
            'headers' => [
                'x-tenant-id' => $this->tenantId->toString(),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testTenantIsolation(): void
    {
        $notificationId = $this->createTestNotification();

        $client = $this->createAuthenticatedClient(tenantId: $this->otherTenantId->toString());

        $client->request('GET', '/api/v1/notifications/'.$notificationId, [
            'headers' => [
                'x-tenant-id' => $this->otherTenantId->toString(),
                'accept' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCollectionPagination(): void
    {
        $client = $this->createAuthenticatedClient();

        for ($i = 0; $i < 35; ++$i) {
            $this->createTestNotification();
        }

        $response = $client->request('GET', '/api/v1/notifications', [
            'headers' => [
                'x-tenant-id' => $this->tenantId->toString(),
                'accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertIsArray($data);

        $this->assertCount(30, $data);
    }

    private function createTestNotification(): string
    {
        $connection = $this->getEntityManager()->getConnection();

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

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        return $notification->id()->toString();
    }

    private function createFailedNotification(): string
    {
        $connection = $this->getEntityManager()->getConnection();

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

        $notification->markAsFailed('Test failure reason');

        $entity = \App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity::fromDomainModel($notification);

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        return $notification->id()->toString();
    }

    private function createSentNotification(): string
    {
        $connection = $this->getEntityManager()->getConnection();

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

        $notification->markAsSent();

        $entity = \App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity::fromDomainModel($notification);

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        return $notification->id()->toString();
    }
}
