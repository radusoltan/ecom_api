<?php

declare(strict_types=1);

namespace App\Tests\Integration\AuditLog\Infrastructure\Persistence\Doctrine\Repository;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\AuditLogId;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineAuditLogRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private AuditLogRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->repository = $container->get(AuditLogRepositoryInterface::class);

        $this->cleanupAuditLogData();
    }

    protected function tearDown(): void
    {
        $this->cleanupAuditLogData();
        parent::tearDown();
    }

    private function cleanupAuditLogData(): void
    {
        $em = $this->getEntityManager();
        $em->getConnection()->executeStatement(
            'DELETE FROM audit_log WHERE tenant_id = :tid',
            ['tid' => $this->tenantId->toString()]
        );
    }

    public function testSaveAndFindById(): void
    {
        $entry = AuditLogEntry::log(
            tenantId: $this->tenantId,
            userId: null,
            actionType: ActionType::create(),
            resourceType: ResourceType::product(),
            resourceId: 'product-123',
            metadata: ['name' => 'Test Product'],
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit'
        );

        $this->repository->save($entry);

        $found = $this->repository->findById($entry->id(), $this->tenantId);

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($entry->id()));
        self::assertTrue($found->tenantId()->equals($this->tenantId));
        self::assertSame('product-123', $found->resourceId());
        self::assertSame('127.0.0.1', $found->ipAddress());
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $nonExistentId = AuditLogId::generate();

        $result = $this->repository->findById($nonExistentId, $this->tenantId);

        self::assertNull($result);
    }

    public function testFindByTenant(): void
    {
        $entry1 = AuditLogEntry::log(
            tenantId: $this->tenantId,
            userId: null,
            actionType: ActionType::create(),
            resourceType: ResourceType::product(),
            resourceId: 'product-1',
        );

        $entry2 = AuditLogEntry::log(
            tenantId: $this->tenantId,
            userId: null,
            actionType: ActionType::update(),
            resourceType: ResourceType::product(),
            resourceId: 'product-2',
        );

        $this->repository->save($entry1);
        $this->repository->save($entry2);

        $entries = $this->repository->findByTenant($this->tenantId);

        self::assertGreaterThanOrEqual(2, count($entries));
    }

    public function testCountEntries(): void
    {
        $entry = AuditLogEntry::log(
            tenantId: $this->tenantId,
            userId: null,
            actionType: ActionType::delete(),
            resourceType: ResourceType::product(),
            resourceId: 'product-del',
        );

        $this->repository->save($entry);

        $count = $this->repository->countEntries($this->tenantId);

        self::assertGreaterThanOrEqual(1, $count);
    }
}
