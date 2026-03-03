<?php

declare(strict_types=1);

namespace App\Tests\Functional\AuditLog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use App\User\Domain\ValueObject\UserId;

final class AuditLogApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private AuditLogRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());
        $this->repository = static::getContainer()->get(AuditLogRepositoryInterface::class);
    }

    public function testGetAuditLogCollectionRequiresAuthentication(): void
    {
        $response = static::createClient()->request('GET', '/api/v1/audit-logs');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetAuditLogCollectionReturnsEntries(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-123');
        $this->createAuditLogEntry(ActionType::update(), ResourceType::order(), 'order-456');

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs', [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testGetSingleAuditLogEntry(): void
    {
        $entry = $this->createAuditLogEntry(ActionType::delete(), ResourceType::customer(), 'customer-789');

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs/' . $entry->id()->toString(), [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'actionType' => 'delete',
            'resourceType' => 'customer',
            'resourceId' => 'customer-789',
        ]);
    }

    public function testFilterAuditLogByActionType(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-1');
        $this->createAuditLogEntry(ActionType::update(), ResourceType::product(), 'product-2');
        $this->createAuditLogEntry(ActionType::delete(), ResourceType::product(), 'product-3');

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs?actionType=create', [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(1, count($data['member']));
        foreach ($data['member'] as $member) {
            $this->assertEquals('create', $member['actionType']);
        }
    }

    public function testFilterAuditLogByResourceType(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::order(), 'order-1');
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-1');
        $this->createAuditLogEntry(ActionType::create(), ResourceType::order(), 'order-2');

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs?resourceType=order', [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(2, count($data['member']));
        foreach ($data['member'] as $member) {
            $this->assertEquals('order', $member['resourceType']);
        }
    }

    public function testFilterAuditLogByUserId(): void
    {
        $userId = UserId::generate();
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-1', $userId);
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-2', UserId::generate());

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs?userId=' . $userId->toString(), [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        foreach ($data['member'] as $member) {
            $this->assertEquals($userId->toString(), $member['userId']);
        }
    }

    public function testFilterAuditLogByResourceId(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::payment(), 'payment-123');
        $this->createAuditLogEntry(ActionType::update(), ResourceType::payment(), 'payment-123');
        $this->createAuditLogEntry(ActionType::create(), ResourceType::payment(), 'payment-456');

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs?resourceId=payment-123', [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(2, count($data['member']));
        foreach ($data['member'] as $member) {
            $this->assertEquals('payment-123', $member['resourceId']);
        }
    }

    public function testFilterAuditLogByDateRange(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::order(), 'order-1');

        $tomorrow = new \DateTimeImmutable('+1 day');

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs?occurredAt[before]=' . $tomorrow->format('Y-m-d'), [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['@type' => 'Collection']);
    }

    public function testPaginationWorks(): void
    {
        for ($i = 0; $i < 60; ++$i) {
            $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-' . $i);
        }

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/v1/audit-logs?page=1', [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(30, count($data['member']));
    }

    public function testAuditLogEntriesAreReadOnly(): void
    {
        $response = static::createClient()->request('POST', '/api/v1/audit-logs', [
            'json' => [
                'actionType' => 'create',
                'resourceType' => 'product',
                'resourceId' => 'test',
            ],
        ]);

        $this->assertResponseStatusCodeSame(405);
    }

    public function testCannotUpdateAuditLogEntry(): void
    {
        $entry = $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-123');

        $response = static::createClient()->request('PATCH', '/api/v1/audit-logs/' . $entry->id()->toString(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['actionType' => 'update'],
        ]);

        $this->assertResponseStatusCodeSame(405);
    }

    public function testCannotDeleteAuditLogEntry(): void
    {
        $entry = $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-123');

        $response = static::createClient()->request('DELETE', '/api/v1/audit-logs/' . $entry->id()->toString());

        $this->assertResponseStatusCodeSame(405);
    }

    private function createAuthenticatedClient(): object
    {
        $client = static::createClient();
        $container = $client->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $email = 'admin@admin.com';
        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername('admin');
            $userEntity->setPassword('$2y$13$dummy.password.hash');
            $userEntity->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($userEntity);
            $entityManager->flush();
        }

        $encoder = $container->get('lexik_jwt_authentication.encoder');

        $token = $encoder->encode([
            'email' => $email,
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        return static::createClient([], ['headers' => ['authorization' => 'Bearer ' . $token]]);
    }

    private function createAuditLogEntry(
        ActionType $actionType,
        ResourceType $resourceType,
        string $resourceId,
        ?UserId $userId = null,
    ): AuditLogEntry {
        $entry = AuditLogEntry::log(
            $this->tenantId,
            $userId ?? UserId::generate(),
            $actionType,
            $resourceType,
            $resourceId,
            ['test' => true],
            '127.0.0.1',
            'Test User Agent'
        );

        $this->repository->save($entry);

        return $entry;
    }
}
