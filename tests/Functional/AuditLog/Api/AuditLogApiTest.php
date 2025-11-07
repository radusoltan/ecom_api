<?php

declare(strict_types=1);

namespace App\Tests\Functional\AuditLog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;

final class AuditLogApiTest extends ApiTestCase
{
    private AuditLogRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(AuditLogRepositoryInterface::class);
        $this->tenantId = TenantId::generate();
    }

    public function testGetAuditLogCollectionRequiresAuthentication(): void
    {
        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetAuditLogCollectionReturnsEntries(): void
    {
        // Create some test data
        $this->createAuditLogEntry(
            ActionType::create(),
            ResourceType::product(),
            'product-123'
        );
        $this->createAuditLogEntry(
            ActionType::update(),
            ResourceType::order(),
            'order-456'
        );

        // Make authenticated request (you'll need to implement authentication)
        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains(['@context' => '/api/contexts/AuditLogEntryEntity']);
        $this->assertJsonContains(['@type' => 'hydra:Collection']);
    }

    public function testGetSingleAuditLogEntry(): void
    {
        $entry = $this->createAuditLogEntry(
            ActionType::delete(),
            ResourceType::customer(),
            'customer-789'
        );

        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities/'.$entry->id()->toString(), [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => '/api/audit_log_entry_entities/'.$entry->id()->toString(),
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

        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities?actionType=create', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertGreaterThanOrEqual(1, $data['hydra:totalItems']);
        foreach ($data['hydra:member'] as $member) {
            $this->assertEquals('create', $member['actionType']);
        }
    }

    public function testFilterAuditLogByResourceType(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::order(), 'order-1');
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-1');
        $this->createAuditLogEntry(ActionType::create(), ResourceType::order(), 'order-2');

        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities?resourceType=order', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertGreaterThanOrEqual(2, $data['hydra:totalItems']);
        foreach ($data['hydra:member'] as $member) {
            $this->assertEquals('order', $member['resourceType']);
        }
    }

    public function testFilterAuditLogByUserId(): void
    {
        $userId = UserId::generate();
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-1', $userId);
        $this->createAuditLogEntry(ActionType::create(), ResourceType::product(), 'product-2', UserId::generate());

        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities?userId='.$userId->toString(), [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        foreach ($data['hydra:member'] as $member) {
            $this->assertEquals($userId->toString(), $member['userId']);
        }
    }

    public function testFilterAuditLogByResourceId(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::payment(), 'payment-123');
        $this->createAuditLogEntry(ActionType::update(), ResourceType::payment(), 'payment-123');
        $this->createAuditLogEntry(ActionType::create(), ResourceType::payment(), 'payment-456');

        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities?resourceId=payment-123', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertGreaterThanOrEqual(2, $data['hydra:totalItems']);
        foreach ($data['hydra:member'] as $member) {
            $this->assertEquals('payment-123', $member['resourceId']);
        }
    }

    public function testFilterAuditLogByDateRange(): void
    {
        $this->createAuditLogEntry(ActionType::create(), ResourceType::order(), 'order-1');

        $tomorrow = new \DateTimeImmutable('+1 day');
        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities?occurredAt[before]='.$tomorrow->format('Y-m-d'), [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['@type' => 'hydra:Collection']);
    }

    public function testPaginationWorks(): void
    {
        // Create more entries than the default page size
        for ($i = 0; $i < 60; ++$i) {
            $this->createAuditLogEntry(
                ActionType::create(),
                ResourceType::product(),
                'product-'.$i
            );
        }

        $response = static::createClient()->request('GET', '/api/audit_log_entry_entities?page=1', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(50, count($data['hydra:member'])); // Default page size is 50
        $this->assertArrayHasKey('hydra:view', $data);
        $this->assertArrayHasKey('hydra:next', $data['hydra:view']);
    }

    public function testAuditLogEntriesAreReadOnly(): void
    {
        // POST should not be allowed
        $response = static::createClient()->request('POST', '/api/audit_log_entry_entities', [
            'json' => [
                'actionType' => 'create',
                'resourceType' => 'product',
                'resourceId' => 'test',
            ],
        ]);

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testCannotUpdateAuditLogEntry(): void
    {
        $entry = $this->createAuditLogEntry(
            ActionType::create(),
            ResourceType::product(),
            'product-123'
        );

        // PATCH should not be allowed
        $response = static::createClient()->request('PATCH', '/api/audit_log_entry_entities/'.$entry->id()->toString(), [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'actionType' => 'update',
            ],
        ]);

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testCannotDeleteAuditLogEntry(): void
    {
        $entry = $this->createAuditLogEntry(
            ActionType::create(),
            ResourceType::product(),
            'product-123'
        );

        // DELETE should not be allowed
        $response = static::createClient()->request('DELETE', '/api/audit_log_entry_entities/'.$entry->id()->toString());

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    private function createAuditLogEntry(
        ActionType $actionType,
        ResourceType $resourceType,
        string $resourceId,
        ?UserId $userId = null
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
