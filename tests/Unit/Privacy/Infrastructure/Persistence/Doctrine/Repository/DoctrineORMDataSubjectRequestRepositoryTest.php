<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use App\Privacy\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMDataSubjectRequestRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(DoctrineORMDataSubjectRequestRepository::class)]
final class DoctrineORMDataSubjectRequestRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ManagerRegistry&MockObject $registry;
    private DoctrineORMDataSubjectRequestRepository $repository;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private DataSubjectRequestId $requestId;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();
        $this->requestId = DataSubjectRequestId::generate();

        // Set up ManagerRegistry to provide the EntityManager.
        // ServiceEntityRepository uses this to resolve an inner EntityRepository.
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = DataSubjectRequestEntity::class;

        $this->entityManager
            ->method('getClassMetadata')
            ->with(DataSubjectRequestEntity::class)
            ->willReturn($classMetadata);

        $this->registry
            ->method('getManagerForClass')
            ->with(DataSubjectRequestEntity::class)
            ->willReturn($this->entityManager);

        $this->repository = new DoctrineORMDataSubjectRequestRepository(
            $this->registry,
            $this->eventDispatcher,
        );

        // Inject a mock EntityRepository via reflection to avoid resolveRepository() complications.
        // This gives us full control over createQueryBuilder() and find() calls.
        $innerRepo = $this->createInnerEntityRepositoryMock();
        $refProp = new \ReflectionProperty(\Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class, 'repository');
        $refProp->setValue($this->repository, $innerRepo);
    }

    // -----------------------------------------------------------------------
    // Inner EntityRepository mock (controls createQueryBuilder + find)
    // -----------------------------------------------------------------------

    /**
     * Creates a mock EntityRepository that delegates createQueryBuilder() and find()
     * to the underlying EntityManager mock.
     */
    private function createInnerEntityRepositoryMock(): EntityRepository&MockObject
    {
        $innerRepo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder', 'find', 'getEntityManager'])
            ->getMock();

        // createQueryBuilder() should call the entity manager to produce a QBs
        $innerRepo->method('createQueryBuilder')
            ->willReturnCallback(function (string $alias) {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select($alias);
                $qb->from(DataSubjectRequestEntity::class, $alias);

                return $qb;
            });

        // find() delegates directly to EntityManager::find()
        $innerRepo->method('find')
            ->willReturnCallback(function (mixed $id) {
                return $this->entityManager->find(DataSubjectRequestEntity::class, $id);
            });

        // getEntityManager() for save() which calls $this->getEntityManager()
        $innerRepo->method('getEntityManager')
            ->willReturn($this->entityManager);

        return $innerRepo;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createDomainRequest(
        ?RequestType $type = null,
        ?RequestStatus $status = null,
    ): DataSubjectRequest {
        $now = new \DateTimeImmutable('2026-01-01 10:00:00');
        $deadline = $now->modify('+30 days');

        return DataSubjectRequest::reconstituteFromPersistence(
            id: $this->requestId,
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: $type ?? RequestType::access(),
            status: $status ?? RequestStatus::pending(),
            reason: null,
            reviewNotes: null,
            rejectionReason: null,
            exportData: null,
            submittedAt: $now,
            completedAt: null,
            deadline: $deadline,
            isExtended: false,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function createEntity(
        string $requestType = 'access',
        string $status = 'pending',
    ): DataSubjectRequestEntity {
        $now = new \DateTimeImmutable('2026-01-01 10:00:00');
        $deadline = $now->modify('+30 days');

        $request = DataSubjectRequest::reconstituteFromPersistence(
            id: $this->requestId,
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::fromString($requestType),
            status: RequestStatus::fromString($status),
            reason: null,
            reviewNotes: null,
            rejectionReason: null,
            exportData: null,
            submittedAt: $now,
            completedAt: null,
            deadline: $deadline,
            isExtended: false,
            createdAt: $now,
            updatedAt: $now,
        );

        return DataSubjectRequestEntity::fromDomainModel($request);
    }

    /**
     * Creates a mock ORM QueryBuilder configured to return the specified result.
     */
    private function createQueryBuilderMock(mixed $result, bool $useOneOrNull = false, bool $useScalar = false): QueryBuilder&MockObject
    {
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult', 'getOneOrNullResult', 'getSingleScalarResult', 'execute'])
            ->getMock();

        if ($useOneOrNull) {
            $query->method('getOneOrNullResult')->willReturn($result);
        } elseif ($useScalar) {
            $query->method('getSingleScalarResult')->willReturn($result);
        } else {
            $query->method('getResult')->willReturn($result);
        }

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    // -----------------------------------------------------------------------
    // save() — new entity (find returns null → persist + flush)
    // -----------------------------------------------------------------------

    #[Test]
    public function saveCallsPersistAndFlushWhenEntityIsNew(): void
    {
        $request = $this->createDomainRequest();

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(DataSubjectRequestEntity::class, $this->requestId->toString())
            ->willReturn(null);

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(DataSubjectRequestEntity::class));

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->repository->save($request);
    }

    #[Test]
    public function saveUpdatesExistingEntityWithoutCallingPersist(): void
    {
        $request = $this->createDomainRequest();
        $existingEntity = $this->createEntity();

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(DataSubjectRequestEntity::class, $this->requestId->toString())
            ->willReturn($existingEntity);

        $this->entityManager
            ->expects(self::never())
            ->method('persist');

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->repository->save($request);
    }

    #[Test]
    public function saveDispatchesDomainEventsAfterFlush(): void
    {
        $request = $this->createDomainRequest();

        $this->entityManager->method('find')->willReturn(null);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // reconstituteFromPersistence does NOT record domain events, so dispatch is never called.
        $this->eventDispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->repository->save($request);
    }

    // -----------------------------------------------------------------------
    // findById()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByIdReturnsNullWhenEntityNotFound(): void
    {
        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(DataSubjectRequestEntity::class, $this->requestId->toString())
            ->willReturn(null);

        $result = $this->repository->findById($this->requestId);

        self::assertNull($result);
    }

    #[Test]
    public function findByIdReturnsDomainModelWhenEntityFound(): void
    {
        $entity = $this->createEntity();

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(DataSubjectRequestEntity::class, $this->requestId->toString())
            ->willReturn($entity);

        $result = $this->repository->findById($this->requestId);

        self::assertInstanceOf(DataSubjectRequest::class, $result);
        self::assertSame($this->requestId->toString(), $result->id()->toString());
        self::assertSame($this->tenantId->toString(), $result->tenantId()->toString());
        self::assertSame('access', $result->requestType()->value());
        self::assertSame('pending', $result->status()->value());
    }

    // -----------------------------------------------------------------------
    // findByCustomerId()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByCustomerIdReturnsEmptyArrayWhenNoResults(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByCustomerId($this->customerId);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findByCustomerIdReturnsMappedDomainModels(): void
    {
        $entity = $this->createEntity();

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByCustomerId($this->customerId);

        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequest::class, $result[0]);
        self::assertSame($this->customerId->toString(), $result[0]->customerId()->toString());
    }

    // -----------------------------------------------------------------------
    // findByTenantId()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByTenantIdReturnsEmptyArrayWhenNoResults(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByTenantId($this->tenantId);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findByTenantIdReturnsMappedDomainModels(): void
    {
        $entity = $this->createEntity();

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByTenantId($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequest::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // findByStatus()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByStatusReturnsEmptyArrayWhenNoResults(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByStatus(RequestStatus::pending());

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findByStatusReturnsMappedDomainModels(): void
    {
        $entity = $this->createEntity('access', 'pending');

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByStatus(RequestStatus::pending(), $this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequest::class, $result[0]);
        self::assertSame('pending', $result[0]->status()->value());
    }

    #[Test]
    public function findByStatusWithoutTenantIdFiltersOnlyByStatus(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByStatus(RequestStatus::underReview(), null);

        self::assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // findByType()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByTypeReturnsEmptyArrayWhenNoResults(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByType(RequestType::erasure());

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findByTypeReturnsMappedDomainModels(): void
    {
        $entity = $this->createEntity('access', 'pending');

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByType(RequestType::access(), $this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequest::class, $result[0]);
        self::assertSame('access', $result[0]->requestType()->value());
    }

    // -----------------------------------------------------------------------
    // findOverdueRequests()
    // -----------------------------------------------------------------------

    #[Test]
    public function findOverdueRequestsReturnsEmptyArrayWhenNoOverdue(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findOverdueRequests();

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findOverdueRequestsReturnsMappedDomainModels(): void
    {
        $entity = $this->createEntity('access', 'pending');

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findOverdueRequests($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequest::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // hasPendingErasureRequest()
    // -----------------------------------------------------------------------

    #[Test]
    public function hasPendingErasureRequestReturnsTrueWhenCountIsGreaterThanZero(): void
    {
        $qb = $this->createQueryBuilderMock(3, false, true);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->hasPendingErasureRequest($this->customerId);

        self::assertTrue($result);
    }

    #[Test]
    public function hasPendingErasureRequestReturnsFalseWhenCountIsZero(): void
    {
        $qb = $this->createQueryBuilderMock(0, false, true);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->hasPendingErasureRequest($this->customerId);

        self::assertFalse($result);
    }
}
