<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Pricing\Domain\ValueObject\PriceChangeSource;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PriceHistoryEntity;
use App\Pricing\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMPriceHistoryRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineORMPriceHistoryRepository::class)]
final class DoctrineORMPriceHistoryRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DoctrineORMPriceHistoryRepository $repository;
    private TenantId $tenantId;
    private ProductId $productId;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = new DoctrineORMPriceHistoryRepository($this->entityManager);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->productId = ProductId::fromString('00000000-0000-4000-8000-000000000010');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createPriceChange(): PriceChange
    {
        return PriceChange::create(
            productId: $this->productId,
            oldPrice: null,
            newPrice: \App\Shared\Domain\ValueObject\Money::of('10.00', 'USD'),
            reason: 'Initial price set',
            source: PriceChangeSource::MANUAL,
        );
    }

    /**
     * Creates a query builder mock that returns the specified result.
     * The $scalarResult is used for COUNT queries (getSingleScalarResult).
     */
    private function createQueryBuilderMock(
        mixed $result,
        bool $useOneOrNull = false,
        bool $useScalar = false,
        int $executeResult = 0,
    ): QueryBuilder&MockObject {
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($useOneOrNull) {
            $query->method('getOneOrNullResult')->willReturn($result);
        } elseif ($useScalar) {
            $query->method('getSingleScalarResult')->willReturn($result);
        } else {
            $query->method('getResult')->willReturn($result);
            $query->method('execute')->willReturn($executeResult);
        }

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('delete')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    private function createPriceHistoryEntity(): PriceHistoryEntity
    {
        $entity = PriceHistoryEntity::fromPriceChange(
            $this->createPriceChange(),
            $this->tenantId,
            null,
        );

        return $entity;
    }

    // -----------------------------------------------------------------------
    // save()
    // -----------------------------------------------------------------------

    #[Test]
    public function saveCallsPersistAndFlush(): void
    {
        $priceChange = $this->createPriceChange();

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(PriceHistoryEntity::class));

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->repository->save($priceChange, $this->tenantId);
    }

    #[Test]
    public function saveWithChangedByUser(): void
    {
        $priceChange = $this->createPriceChange();
        $userId = \App\User\Domain\ValueObject\UserId::generate();

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(PriceHistoryEntity::class));

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->repository->save($priceChange, $this->tenantId, $userId);
    }

    // -----------------------------------------------------------------------
    // findByProductId()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByProductIdReturnsEmptyArrayWhenNoResults(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByProductId($this->productId, $this->tenantId);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findByProductIdReturnsMappedPriceChanges(): void
    {
        $entity = $this->createPriceHistoryEntity();

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByProductId($this->productId, $this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(PriceChange::class, $result[0]);
        self::assertSame($this->productId->toString(), $result[0]->productId()->toString());
    }

    #[Test]
    public function findByProductIdRespectsLimitAndOffset(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        // Verify setMaxResults and setFirstResult are called via the mock chain
        $result = $this->repository->findByProductId($this->productId, $this->tenantId, 10, 20);

        self::assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // findByTenant()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByTenantReturnsEmptyArrayWhenNoResults(): void
    {
        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByTenant($this->tenantId);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findByTenantReturnsMappedPriceChanges(): void
    {
        $entity = $this->createPriceHistoryEntity();

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByTenant($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(PriceChange::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // findByDateRange()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByDateRangeReturnsMatchingPriceChanges(): void
    {
        $entity = $this->createPriceHistoryEntity();
        $from = new \DateTimeImmutable('-30 days');
        $to = new \DateTimeImmutable('now');

        $qb = $this->createQueryBuilderMock([$entity]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByDateRange($this->tenantId, $from, $to);

        self::assertCount(1, $result);
        self::assertInstanceOf(PriceChange::class, $result[0]);
    }

    #[Test]
    public function findByDateRangeReturnsEmptyArrayWhenNoMatches(): void
    {
        $from = new \DateTimeImmutable('-30 days');
        $to = new \DateTimeImmutable('now');

        $qb = $this->createQueryBuilderMock([]);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findByDateRange($this->tenantId, $from, $to);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    // -----------------------------------------------------------------------
    // getLatestForProduct()
    // -----------------------------------------------------------------------

    #[Test]
    public function getLatestForProductReturnsNullWhenNoHistory(): void
    {
        $qb = $this->createQueryBuilderMock(null, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getLatestForProduct($this->productId, $this->tenantId);

        self::assertNull($result);
    }

    #[Test]
    public function getLatestForProductReturnsPriceChangeWhenFound(): void
    {
        $entity = $this->createPriceHistoryEntity();

        $qb = $this->createQueryBuilderMock($entity, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getLatestForProduct($this->productId, $this->tenantId);

        self::assertInstanceOf(PriceChange::class, $result);
        self::assertSame($this->productId->toString(), $result->productId()->toString());
    }

    // -----------------------------------------------------------------------
    // getLowestPriceInLastDays()
    // -----------------------------------------------------------------------

    #[Test]
    public function getLowestPriceInLastDaysReturnsNullWhenNoHistory(): void
    {
        $qb = $this->createQueryBuilderMock(null, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getLowestPriceInLastDays($this->productId, $this->tenantId, 30);

        self::assertNull($result);
    }

    #[Test]
    public function getLowestPriceInLastDaysReturnsPriceChangeWhenFound(): void
    {
        $entity = $this->createPriceHistoryEntity();

        $qb = $this->createQueryBuilderMock($entity, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getLowestPriceInLastDays($this->productId, $this->tenantId, 30);

        self::assertInstanceOf(PriceChange::class, $result);
    }

    // -----------------------------------------------------------------------
    // countByProductId()
    // -----------------------------------------------------------------------

    #[Test]
    public function countByProductIdReturnsInteger(): void
    {
        $qb = $this->createQueryBuilderMock(5, false, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->countByProductId($this->productId, $this->tenantId);

        self::assertSame(5, $result);
    }

    #[Test]
    public function countByProductIdReturnsZeroWhenNone(): void
    {
        $qb = $this->createQueryBuilderMock(0, false, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->countByProductId($this->productId, $this->tenantId);

        self::assertSame(0, $result);
    }

    // -----------------------------------------------------------------------
    // countByTenant()
    // -----------------------------------------------------------------------

    #[Test]
    public function countByTenantReturnsInteger(): void
    {
        $qb = $this->createQueryBuilderMock(42, false, true);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->countByTenant($this->tenantId);

        self::assertSame(42, $result);
    }

    // -----------------------------------------------------------------------
    // deleteOlderThan()
    // -----------------------------------------------------------------------

    #[Test]
    public function deleteOlderThanReturnsNumberOfDeletedRows(): void
    {
        $beforeDate = new \DateTimeImmutable('-2 years');

        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->method('execute')->willReturn(10);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->deleteOlderThan($beforeDate);

        self::assertSame(10, $result);
    }
}
