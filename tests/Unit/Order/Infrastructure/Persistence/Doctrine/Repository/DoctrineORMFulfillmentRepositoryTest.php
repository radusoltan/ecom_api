<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\Persistence\Doctrine\Repository;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Order\Infrastructure\Persistence\Doctrine\Entity\FulfillmentEntity;
use App\Order\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMFulfillmentRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(DoctrineORMFulfillmentRepository::class)]
#[Group('order')]
final class DoctrineORMFulfillmentRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private DoctrineORMFulfillmentRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->repository = new DoctrineORMFulfillmentRepository(
            $this->entityManager,
            $this->eventDispatcher,
        );
    }

    // ---------------------------------------------------------------
    // Implements correct interface
    // ---------------------------------------------------------------

    #[Test]
    public function itImplementsFulfillmentRepositoryInterface(): void
    {
        self::assertInstanceOf(FulfillmentRepositoryInterface::class, $this->repository);
    }

    // ---------------------------------------------------------------
    // save – new entity (persist path)
    // ---------------------------------------------------------------

    #[Test]
    public function itPersistsNewFulfillmentWhenNoEntityExists(): void
    {
        $fulfillment = $this->buildFulfillment();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('find')
            ->with($fulfillment->id()->toString())
            ->willReturn(null);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $this->entityManager->expects(self::once())
            ->method('persist');

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->repository->save($fulfillment);
    }

    // ---------------------------------------------------------------
    // save – existing entity (update path)
    // ---------------------------------------------------------------

    #[Test]
    public function itUpdatesExistingFulfillmentWhenEntityExists(): void
    {
        $fulfillment = $this->buildFulfillment();
        $existingEntity = $this->createMock(FulfillmentEntity::class);
        $existingEntity->expects(self::once())
            ->method('updateFromDomainModel')
            ->with($fulfillment);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('find')
            ->with($fulfillment->id()->toString())
            ->willReturn($existingEntity);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $this->entityManager->expects(self::never())
            ->method('persist');

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->repository->save($fulfillment);
    }

    // ---------------------------------------------------------------
    // findById – found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsDomainModelWhenFulfillmentFound(): void
    {
        $fulfillmentId = FulfillmentId::generate();
        $domainFulfillment = $this->buildFulfillment($fulfillmentId);
        $entity = $this->createMock(FulfillmentEntity::class);
        $entity->expects(self::once())
            ->method('toDomainModel')
            ->willReturn($domainFulfillment);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('find')
            ->with($fulfillmentId->toString())
            ->willReturn($entity);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findById($fulfillmentId);

        self::assertSame($domainFulfillment, $result);
    }

    // ---------------------------------------------------------------
    // findById – not found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenFulfillmentNotFound(): void
    {
        $fulfillmentId = FulfillmentId::generate();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('find')
            ->with($fulfillmentId->toString())
            ->willReturn(null);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findById($fulfillmentId);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // findByOrderId – found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsFulfillmentByOrderId(): void
    {
        $orderId = OrderId::generate();
        $fulfillment = $this->buildFulfillment();
        $entity = $this->createMock(FulfillmentEntity::class);
        $entity->method('toDomainModel')->willReturn($fulfillment);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findOneBy')
            ->with(['orderId' => $orderId->toString()])
            ->willReturn($entity);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByOrderId($orderId);

        self::assertSame($fulfillment, $result);
    }

    // ---------------------------------------------------------------
    // findByOrderId – not found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenNoFulfillmentForOrderId(): void
    {
        $orderId = OrderId::generate();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByOrderId($orderId);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // findByTenant
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsMappedDomainModelsForTenant(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $fulfillment = $this->buildFulfillment();
        $entity = $this->createMock(FulfillmentEntity::class);
        $entity->method('toDomainModel')->willReturn($fulfillment);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findBy')
            ->with(
                ['tenantId' => $tenantId->toString()],
                ['createdAt' => 'DESC']
            )
            ->willReturn([$entity]);

        $this->entityManager->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByTenant($tenantId);

        self::assertCount(1, $result);
        self::assertSame($fulfillment, $result[0]);
    }

    // ---------------------------------------------------------------
    // findByStatus
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsFulfillmentsByStatus(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $status = FulfillmentStatus::assigned();
        $fulfillment = $this->buildFulfillment();
        $entity = $this->createMock(FulfillmentEntity::class);
        $entity->method('toDomainModel')->willReturn($fulfillment);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findBy')
            ->with(
                ['tenantId' => $tenantId->toString(), 'status' => $status->value()],
                ['createdAt' => 'DESC']
            )
            ->willReturn([$entity]);

        $this->entityManager->method('getRepository')
            ->with(FulfillmentEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByStatus($status, $tenantId);

        self::assertCount(1, $result);
    }

    // ---------------------------------------------------------------
    // findInProgress
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsFulfillmentsInProgress(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $fulfillment = $this->buildFulfillment();
        $entity = $this->createMock(FulfillmentEntity::class);
        $entity->method('toDomainModel')->willReturn($fulfillment);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$entity]);

        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findInProgress($tenantId);

        self::assertCount(1, $result);
        self::assertSame($fulfillment, $result[0]);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildFulfillment(?FulfillmentId $id = null): Fulfillment
    {
        return Fulfillment::start(
            id: $id ?? FulfillmentId::generate(),
            orderId: OrderId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );
    }
}
