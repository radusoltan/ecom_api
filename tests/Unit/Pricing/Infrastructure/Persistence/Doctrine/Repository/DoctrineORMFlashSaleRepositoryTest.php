<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Model\FlashSaleStatus;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Pricing\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMFlashSaleRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[CoversClass(DoctrineORMFlashSaleRepository::class)]
final class DoctrineORMFlashSaleRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private DoctrineORMFlashSaleRepository $repository;
    private TenantId $tenantId;
    private FlashSaleId $flashSaleId;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->repository = new DoctrineORMFlashSaleRepository(
            $this->entityManager,
            $this->eventDispatcher,
        );
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->flashSaleId = FlashSaleId::generate();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createFlashSale(): FlashSale
    {
        $now = new \DateTimeImmutable();
        $start = $now->modify('+1 hour');
        $end = $now->modify('+3 hours');

        return FlashSale::reconstituteFromPersistence(
            id: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Summer Flash Sale',
            productIds: [ProductId::fromString('00000000-0000-4000-8000-000000000010')],
            discount: Discount::percentage(20.0),
            startTime: $start,
            endTime: $end,
            status: FlashSaleStatus::SCHEDULED,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function createQueryBuilderMock(mixed $result, bool $useOneOrNull = false): QueryBuilder&MockObject
    {
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($useOneOrNull) {
            $query->method('getOneOrNullResult')->willReturn($result);
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
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('expr')->willReturn(new \Doctrine\ORM\Query\Expr());
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    // -----------------------------------------------------------------------
    // save() — new entity (find returns null → persist + flush)
    // -----------------------------------------------------------------------

    #[Test]
    public function saveCallsPersistAndFlushWhenEntityIsNew(): void
    {
        $flashSale = $this->createFlashSale();

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(FlashSaleEntity::class, $this->flashSaleId->toString())
            ->willReturn(null);

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(FlashSaleEntity::class));

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->repository->save($flashSale);
    }

    #[Test]
    public function saveUpdatesExistingEntityWithoutCallingPersist(): void
    {
        $flashSale = $this->createFlashSale();

        $existingEntity = new FlashSaleEntity();
        $existingEntity->setId($this->flashSaleId->toString());
        $existingEntity->setTenantId($this->tenantId->toString());
        $existingEntity->setName('Summer Flash Sale');
        $existingEntity->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $existingEntity->setDiscountType('percentage');
        $existingEntity->setDiscountValue(20.0);
        $existingEntity->setStartTime((new \DateTimeImmutable())->modify('+1 hour'));
        $existingEntity->setEndTime((new \DateTimeImmutable())->modify('+3 hours'));
        $existingEntity->setStatus('scheduled');
        $existingEntity->setCreatedAt(new \DateTimeImmutable());
        $existingEntity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(FlashSaleEntity::class, $this->flashSaleId->toString())
            ->willReturn($existingEntity);

        $this->entityManager
            ->expects(self::never())
            ->method('persist');

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->repository->save($flashSale);
    }

    #[Test]
    public function saveDispatchesDomainEvents(): void
    {
        $flashSale = $this->createFlashSale();
        // FlashSale::reconstituteFromPersistence does NOT record events, so pullDomainEvents() → []
        // We verify eventDispatcher->dispatch is never called for a clean aggregate.
        $this->entityManager->method('find')->willReturn(null);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->eventDispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->repository->save($flashSale);
    }

    // -----------------------------------------------------------------------
    // findById()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByIdReturnsNullWhenQueryBuilderReturnsNull(): void
    {
        $qb = $this->createQueryBuilderMock(null, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findById($this->flashSaleId, $this->tenantId);

        self::assertNull($result);
    }

    #[Test]
    public function findByIdReturnsFlashSaleWhenEntityFound(): void
    {
        $now = new \DateTimeImmutable();
        $entity = new FlashSaleEntity();
        $entity->setId($this->flashSaleId->toString());
        $entity->setTenantId($this->tenantId->toString());
        $entity->setName('Summer Flash Sale');
        $entity->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(20.0);
        $entity->setStartTime($now->modify('+1 hour'));
        $entity->setEndTime($now->modify('+3 hours'));
        $entity->setStatus('scheduled');
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        $qb = $this->createQueryBuilderMock($entity, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findById($this->flashSaleId, $this->tenantId);

        self::assertInstanceOf(FlashSale::class, $result);
        self::assertSame($this->flashSaleId->toString(), $result->id()->toString());
        self::assertSame($this->tenantId->toString(), $result->tenantId()->toString());
    }

    // -----------------------------------------------------------------------
    // findActiveByTenant()
    // -----------------------------------------------------------------------

    #[Test]
    public function findActiveByTenantReturnsEmptyArrayWhenNoEntities(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findActiveByTenant($this->tenantId);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findActiveByTenantReturnsMappedFlashSales(): void
    {
        $now = new \DateTimeImmutable();
        $entity = new FlashSaleEntity();
        $entity->setId($this->flashSaleId->toString());
        $entity->setTenantId($this->tenantId->toString());
        $entity->setName('Active Flash Sale');
        $entity->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(15.0);
        $entity->setStartTime($now->modify('-1 hour'));
        $entity->setEndTime($now->modify('+2 hours'));
        $entity->setStatus('active');
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findActiveByTenant($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(FlashSale::class, $result[0]);
        self::assertSame('Active Flash Sale', $result[0]->name());
    }

    // -----------------------------------------------------------------------
    // findUpcoming()
    // -----------------------------------------------------------------------

    #[Test]
    public function findUpcomingReturnsEmptyArrayWhenNoEntities(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findUpcoming($this->tenantId);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findUpcomingReturnsMappedFlashSales(): void
    {
        $now = new \DateTimeImmutable();
        $entity = new FlashSaleEntity();
        $entity->setId($this->flashSaleId->toString());
        $entity->setTenantId($this->tenantId->toString());
        $entity->setName('Upcoming Sale');
        $entity->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(10.0);
        $entity->setStartTime($now->modify('+24 hours'));
        $entity->setEndTime($now->modify('+48 hours'));
        $entity->setStatus('scheduled');
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findUpcoming($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(FlashSale::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // findAll()
    // -----------------------------------------------------------------------

    #[Test]
    public function findAllReturnsAllFlashSalesForTenant(): void
    {
        $now = new \DateTimeImmutable();
        $entity1 = new FlashSaleEntity();
        $entity1->setId(FlashSaleId::generate()->toString());
        $entity1->setTenantId($this->tenantId->toString());
        $entity1->setName('Sale One');
        $entity1->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $entity1->setDiscountType('percentage');
        $entity1->setDiscountValue(10.0);
        $entity1->setStartTime($now->modify('+1 hour'));
        $entity1->setEndTime($now->modify('+3 hours'));
        $entity1->setStatus('scheduled');
        $entity1->setCreatedAt($now);
        $entity1->setUpdatedAt($now);

        $entity2 = new FlashSaleEntity();
        $entity2->setId(FlashSaleId::generate()->toString());
        $entity2->setTenantId($this->tenantId->toString());
        $entity2->setName('Sale Two');
        $entity2->setProductIds(['00000000-0000-4000-8000-000000000011']);
        $entity2->setDiscountType('percentage');
        $entity2->setDiscountValue(20.0);
        $entity2->setStartTime($now->modify('+2 hours'));
        $entity2->setEndTime($now->modify('+4 hours'));
        $entity2->setStatus('scheduled');
        $entity2->setCreatedAt($now);
        $entity2->setUpdatedAt($now);

        $qb = $this->createQueryBuilderMock([$entity1, $entity2]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findAll($this->tenantId);

        self::assertCount(2, $result);
        self::assertInstanceOf(FlashSale::class, $result[0]);
        self::assertInstanceOf(FlashSale::class, $result[1]);
    }

    // -----------------------------------------------------------------------
    // findScheduledToActivateAt()
    // -----------------------------------------------------------------------

    #[Test]
    public function findScheduledToActivateAtReturnsMatchingFlashSales(): void
    {
        $now = new \DateTimeImmutable();
        $entity = new FlashSaleEntity();
        $entity->setId($this->flashSaleId->toString());
        $entity->setTenantId($this->tenantId->toString());
        $entity->setName('Ready to Activate');
        $entity->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(25.0);
        $entity->setStartTime($now->modify('-5 minutes'));
        $entity->setEndTime($now->modify('+3 hours'));
        $entity->setStatus('scheduled');
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findScheduledToActivateAt($now);

        self::assertCount(1, $result);
        self::assertInstanceOf(FlashSale::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // findActiveToEndAt()
    // -----------------------------------------------------------------------

    #[Test]
    public function findActiveToEndAtReturnsMatchingFlashSales(): void
    {
        $now = new \DateTimeImmutable();
        $entity = new FlashSaleEntity();
        $entity->setId($this->flashSaleId->toString());
        $entity->setTenantId($this->tenantId->toString());
        $entity->setName('Ending Soon');
        $entity->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(30.0);
        $entity->setStartTime($now->modify('-2 hours'));
        $entity->setEndTime($now->modify('-1 minute'));
        $entity->setStatus('active');
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findActiveToEndAt($now);

        self::assertCount(1, $result);
        self::assertInstanceOf(FlashSale::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // findActiveByProductId()
    // -----------------------------------------------------------------------

    #[Test]
    public function findActiveByProductIdReturnsNullWhenNoMatch(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000010');

        $qb = $this->createQueryBuilderMock(null, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findActiveByProductId($productId, $this->tenantId);

        self::assertNull($result);
    }

    #[Test]
    public function findActiveByProductIdReturnsFlashSaleWhenFound(): void
    {
        $now = new \DateTimeImmutable();
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000010');

        $entity = new FlashSaleEntity();
        $entity->setId($this->flashSaleId->toString());
        $entity->setTenantId($this->tenantId->toString());
        $entity->setName('Active Product Sale');
        $entity->setProductIds([$productId->toString()]);
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(15.0);
        $entity->setStartTime($now->modify('-1 hour'));
        $entity->setEndTime($now->modify('+2 hours'));
        $entity->setStatus('active');
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        $qb = $this->createQueryBuilderMock($entity, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findActiveByProductId($productId, $this->tenantId);

        self::assertInstanceOf(FlashSale::class, $result);
        self::assertSame('Active Product Sale', $result->name());
    }

    // -----------------------------------------------------------------------
    // delete()
    // -----------------------------------------------------------------------

    #[Test]
    public function deleteCallsRemoveAndFlushWhenEntityExists(): void
    {
        $flashSale = $this->createFlashSale();

        $entity = new FlashSaleEntity();
        $entity->setId($this->flashSaleId->toString());
        $entity->setTenantId($this->tenantId->toString());
        $entity->setName('Summer Flash Sale');
        $entity->setProductIds(['00000000-0000-4000-8000-000000000010']);
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(20.0);
        $entity->setStartTime((new \DateTimeImmutable())->modify('+1 hour'));
        $entity->setEndTime((new \DateTimeImmutable())->modify('+3 hours'));
        $entity->setStatus('scheduled');
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(FlashSaleEntity::class, $this->flashSaleId->toString())
            ->willReturn($entity);

        $this->entityManager
            ->expects(self::once())
            ->method('remove')
            ->with($entity);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->repository->delete($flashSale);
    }

    #[Test]
    public function deleteDoesNothingWhenEntityNotFound(): void
    {
        $flashSale = $this->createFlashSale();

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(FlashSaleEntity::class, $this->flashSaleId->toString())
            ->willReturn(null);

        $this->entityManager
            ->expects(self::never())
            ->method('remove');

        $this->entityManager
            ->expects(self::never())
            ->method('flush');

        $this->repository->delete($flashSale);
    }
}
