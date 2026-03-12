<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\WarehouseEntity;
use App\Inventory\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMWarehouseRepository;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(DoctrineORMWarehouseRepository::class)]
final class DoctrineORMWarehouseRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $eventBus;
    private DoctrineORMWarehouseRepository $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventBus = $this->createMock(MessageBusInterface::class);
        $this->repository = new DoctrineORMWarehouseRepository(
            $this->entityManager,
            $this->eventBus,
            new CacheService(new TagAwareAdapter(new ArrayAdapter()), new NullLogger())
        );
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createEntityRepo(
        ?WarehouseEntity $findResult = null,
        ?WarehouseEntity $findOneByResult = null,
        array $findByResult = [],
        int $countResult = 0,
    ): EntityRepository&MockObject {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($findResult);
        $repo->method('findOneBy')->willReturn($findOneByResult);
        $repo->method('findBy')->willReturn($findByResult);
        $repo->method('count')->willReturn($countResult);

        return $repo;
    }

    private function createWarehouseMock(array $events = []): Warehouse&MockObject
    {
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('id')->willReturn(WarehouseId::fromString('00000000-0000-4000-8000-000000000010'));
        $warehouse->method('popEvents')->willReturn($events);

        return $warehouse;
    }

    private function createWarehouseEntityMock(): WarehouseEntity&MockObject
    {
        $entity = $this->createMock(WarehouseEntity::class);
        $domainModel = $this->createMock(Warehouse::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        return $entity;
    }

    // -----------------------------------------------------------------------
    // save() - new entity
    // -----------------------------------------------------------------------

    #[Test]
    public function saveCreatesNewEntityWhenNotFound(): void
    {
        $warehouse = $this->createWarehouseMock();
        $repo = $this->createEntityRepo(findResult: null);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($warehouse);
    }

    // -----------------------------------------------------------------------
    // save() - existing entity
    // -----------------------------------------------------------------------

    #[Test]
    public function saveUpdatesExistingEntity(): void
    {
        $warehouse = $this->createWarehouseMock();
        $existingEntity = $this->createMock(WarehouseEntity::class);
        $existingEntity->expects(self::once())->method('updateFromDomainModel')->with($warehouse);

        $repo = $this->createEntityRepo(findResult: $existingEntity);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($warehouse);
    }

    // -----------------------------------------------------------------------
    // save() - dispatches events
    // -----------------------------------------------------------------------

    #[Test]
    public function saveDispatchesDomainEvents(): void
    {
        $event = new \stdClass();
        $warehouse = $this->createWarehouseMock([$event]);
        $repo = $this->createEntityRepo(findResult: null);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $this->eventBus->expects(self::once())
            ->method('dispatch')
            ->with($event)
            ->willReturn(new Envelope($event));

        $this->repository->save($warehouse);
    }

    // -----------------------------------------------------------------------
    // findById()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByIdReturnsNullWhenNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $result = $this->repository->findById(WarehouseId::fromString('00000000-0000-4000-8000-000000000010'));

        self::assertNull($result);
    }

    #[Test]
    public function findByIdReturnsDomainModelWhenFound(): void
    {
        $entity = $this->createWarehouseEntityMock();
        $this->entityManager->method('find')->willReturn($entity);

        $result = $this->repository->findById(WarehouseId::fromString('00000000-0000-4000-8000-000000000010'));

        self::assertInstanceOf(Warehouse::class, $result);
    }

    // -----------------------------------------------------------------------
    // findByCodeAndTenant()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByCodeAndTenantReturnsNullWhenNotFound(): void
    {
        $repo = $this->createEntityRepo(findOneByResult: null);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findByCodeAndTenant(WarehouseCode::fromString('WH001'), $this->tenantId);

        self::assertNull($result);
    }

    #[Test]
    public function findByCodeAndTenantReturnsDomainModel(): void
    {
        $entity = $this->createWarehouseEntityMock();
        $repo = $this->createEntityRepo(findOneByResult: $entity);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findByCodeAndTenant(WarehouseCode::fromString('WH001'), $this->tenantId);

        self::assertInstanceOf(Warehouse::class, $result);
    }

    #[Test]
    public function findByCodeAndTenantCachesRepeatedLookups(): void
    {
        $entity = $this->createWarehouseEntityMock();
        $repo = $this->createEntityRepo(findOneByResult: $entity);
        $repo->expects(self::once())
            ->method('findOneBy')
            ->with([
                'code' => 'WH001',
                'tenantId' => $this->tenantId->toString(),
            ])
            ->willReturn($entity);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->willReturn($repo);

        $first = $this->repository->findByCodeAndTenant(WarehouseCode::fromString('WH001'), $this->tenantId);
        $second = $this->repository->findByCodeAndTenant(WarehouseCode::fromString('WH001'), $this->tenantId);

        self::assertInstanceOf(Warehouse::class, $first);
        self::assertInstanceOf(Warehouse::class, $second);
    }

    // -----------------------------------------------------------------------
    // findByTenant()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByTenantReturnsEmptyArrayWhenNone(): void
    {
        $repo = $this->createEntityRepo(findByResult: []);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findByTenant($this->tenantId);

        self::assertSame([], $result);
    }

    #[Test]
    public function findByTenantReturnsMappedDomainModels(): void
    {
        $entity = $this->createWarehouseEntityMock();
        $repo = $this->createEntityRepo(findByResult: [$entity]);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findByTenant($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(Warehouse::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // findActiveByTenant()
    // -----------------------------------------------------------------------

    #[Test]
    public function findActiveByTenantReturnsEmptyArrayWhenNone(): void
    {
        $repo = $this->createEntityRepo(findByResult: []);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findActiveByTenant($this->tenantId);

        self::assertSame([], $result);
    }

    #[Test]
    public function findActiveByTenantReturnsMappedDomainModels(): void
    {
        $entity = $this->createWarehouseEntityMock();
        $repo = $this->createEntityRepo(findByResult: [$entity]);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findActiveByTenant($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(Warehouse::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // codeExistsForTenant()
    // -----------------------------------------------------------------------

    #[Test]
    public function codeExistsForTenantReturnsTrueWhenExists(): void
    {
        $repo = $this->createEntityRepo(countResult: 1);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->codeExistsForTenant(WarehouseCode::fromString('WH001'), $this->tenantId);

        self::assertTrue($result);
    }

    #[Test]
    public function codeExistsForTenantReturnsFalseWhenNotExists(): void
    {
        $repo = $this->createEntityRepo(countResult: 0);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->codeExistsForTenant(WarehouseCode::fromString('WH999'), $this->tenantId);

        self::assertFalse($result);
    }
}
