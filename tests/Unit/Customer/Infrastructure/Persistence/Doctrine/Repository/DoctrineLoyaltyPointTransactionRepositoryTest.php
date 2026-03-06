<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\LoyaltyPointTransactionEntity;
use App\Customer\Infrastructure\Persistence\Doctrine\Repository\DoctrineLoyaltyPointTransactionRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineLoyaltyPointTransactionRepository::class)]
final class DoctrineLoyaltyPointTransactionRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DoctrineLoyaltyPointTransactionRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')
            ->willReturn(new ClassMetadata(LoyaltyPointTransactionEntity::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        $this->repository = new DoctrineLoyaltyPointTransactionRepository($registry);
    }

    private function createQueryBuilderMock(mixed $result = []): QueryBuilder&MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn(is_array($result) ? $result : []);
        $query->method('getSingleScalarResult')->willReturn(is_int($result) ? $result : 0);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    private function createTransactionMock(): LoyaltyPointTransaction&MockObject
    {
        $id = $this->createMock(LoyaltyPointTransactionId::class);
        $id->method('toString')->willReturn('01JQKM0000GGGG000000000001');

        $customerId = $this->createMock(CustomerId::class);
        $customerId->method('toString')->willReturn('01JQKM0000GGGG000000000002');

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $transaction = $this->createMock(LoyaltyPointTransaction::class);
        $transaction->method('id')->willReturn($id);
        $transaction->method('customerId')->willReturn($customerId);
        $transaction->method('tenantId')->willReturn($tenantId);
        $transaction->method('type')->willReturn(TransactionType::EARNED);
        $transaction->method('points')->willReturn(100);
        $transaction->method('balanceAfter')->willReturn(500);
        $transaction->method('reason')->willReturn('Purchase reward');
        $transaction->method('orderId')->willReturn(null);
        $transaction->method('expiresAt')->willReturn(null);
        $transaction->method('createdAt')->willReturn(new \DateTimeImmutable());

        return $transaction;
    }

    // ---- save() tests ----

    #[Test]
    public function savePersistsAndFlushes(): void
    {
        $transaction = $this->createTransactionMock();

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($transaction);
    }

    // ---- findById() tests ----

    #[Test]
    public function findByIdReturnsNullWhenNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $id = $this->createMock(LoyaltyPointTransactionId::class);
        $id->method('toString')->willReturn('01JQKM0000GGGG000000000001');

        self::assertNull($this->repository->findById($id));
    }

    #[Test]
    public function findByIdReturnsDomainModel(): void
    {
        $domainModel = $this->createMock(LoyaltyPointTransaction::class);
        $entity = $this->createMock(LoyaltyPointTransactionEntity::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $this->entityManager->method('find')->willReturn($entity);

        $id = $this->createMock(LoyaltyPointTransactionId::class);
        $id->method('toString')->willReturn('01JQKM0000GGGG000000000001');

        self::assertSame($domainModel, $this->repository->findById($id));
    }

    // ---- findByCustomerId() tests ----

    #[Test]
    public function findByCustomerIdReturnsEmptyArray(): void
    {
        $qb = $this->createQueryBuilderMock([]);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $customerId = $this->createMock(CustomerId::class);
        $customerId->method('toString')->willReturn('01JQKM0000GGGG000000000002');
        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        self::assertSame([], $this->repository->findByCustomerId($customerId, $tenantId));
    }

    #[Test]
    public function findByCustomerIdReturnsMappedModels(): void
    {
        $domainModel = $this->createMock(LoyaltyPointTransaction::class);
        $entity = $this->createMock(LoyaltyPointTransactionEntity::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $qb = $this->createQueryBuilderMock([$entity]);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $customerId = $this->createMock(CustomerId::class);
        $customerId->method('toString')->willReturn('01JQKM0000GGGG000000000002');
        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $result = $this->repository->findByCustomerId($customerId, $tenantId);
        self::assertCount(1, $result);
        self::assertInstanceOf(LoyaltyPointTransaction::class, $result[0]);
    }

    // ---- findByCustomerIdPaginated() tests ----

    #[Test]
    public function findByCustomerIdPaginatedThrowsOnInvalidPage(): void
    {
        $customerId = $this->createMock(CustomerId::class);
        $tenantId = $this->createMock(TenantId::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be greater than 0');

        $this->repository->findByCustomerIdPaginated($customerId, $tenantId, 0, 10);
    }

    #[Test]
    public function findByCustomerIdPaginatedThrowsOnLimitTooLow(): void
    {
        $customerId = $this->createMock(CustomerId::class);
        $tenantId = $this->createMock(TenantId::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        $this->repository->findByCustomerIdPaginated($customerId, $tenantId, 1, 0);
    }

    #[Test]
    public function findByCustomerIdPaginatedThrowsOnLimitTooHigh(): void
    {
        $customerId = $this->createMock(CustomerId::class);
        $tenantId = $this->createMock(TenantId::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        $this->repository->findByCustomerIdPaginated($customerId, $tenantId, 1, 101);
    }

    #[Test]
    public function findByCustomerIdPaginatedReturnsResults(): void
    {
        $domainModel = $this->createMock(LoyaltyPointTransaction::class);
        $entity = $this->createMock(LoyaltyPointTransactionEntity::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $qb = $this->createQueryBuilderMock([$entity]);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $customerId = $this->createMock(CustomerId::class);
        $customerId->method('toString')->willReturn('01JQKM0000GGGG000000000002');
        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $result = $this->repository->findByCustomerIdPaginated($customerId, $tenantId, 1, 10);
        self::assertCount(1, $result);
    }

    // ---- countByCustomerId() tests ----

    #[Test]
    public function countByCustomerIdReturnsCount(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(5);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $customerId = $this->createMock(CustomerId::class);
        $customerId->method('toString')->willReturn('01JQKM0000GGGG000000000002');
        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        self::assertSame(5, $this->repository->countByCustomerId($customerId, $tenantId));
    }

    // ---- sumPointsByCustomerId() tests ----

    #[Test]
    public function sumPointsByCustomerIdReturnsSum(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(150);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $customerId = $this->createMock(CustomerId::class);
        $customerId->method('toString')->willReturn('01JQKM0000GGGG000000000002');
        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        self::assertSame(150, $this->repository->sumPointsByCustomerId($customerId, $tenantId));
    }

    #[Test]
    public function sumPointsByCustomerIdReturnsZeroWhenNull(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $customerId = $this->createMock(CustomerId::class);
        $customerId->method('toString')->willReturn('01JQKM0000GGGG000000000002');
        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        self::assertSame(0, $this->repository->sumPointsByCustomerId($customerId, $tenantId));
    }
}
