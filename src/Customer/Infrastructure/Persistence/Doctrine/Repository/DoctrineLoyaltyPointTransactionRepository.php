<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\Repository\LoyaltyPointTransactionRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\LoyaltyPointTransactionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Doctrine implementation of LoyaltyPointTransactionRepositoryInterface.
 *
 * @extends ServiceEntityRepository<LoyaltyPointTransactionEntity>
 */
final class DoctrineLoyaltyPointTransactionRepository extends ServiceEntityRepository implements LoyaltyPointTransactionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoyaltyPointTransactionEntity::class);
    }

    public function save(LoyaltyPointTransaction $transaction): void
    {
        $entity = LoyaltyPointTransactionEntity::fromDomainModel($transaction);

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function findById(LoyaltyPointTransactionId $id): ?LoyaltyPointTransaction
    {
        $entity = $this->find($id->toString());

        if (null === $entity) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): array
    {
        $entities = $this->createQueryBuilder('t')
            ->where('t.customerId = :customerId')
            ->andWhere('t.tenantId = :tenantId')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn (LoyaltyPointTransactionEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByCustomerIdPaginated(
        CustomerId $customerId,
        TenantId $tenantId,
        int $page,
        int $limit,
    ): array {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100');
        }

        $offset = ($page - 1) * $limit;

        $entities = $this->createQueryBuilder('t')
            ->where('t.customerId = :customerId')
            ->andWhere('t.tenantId = :tenantId')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            fn (LoyaltyPointTransactionEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function countByCustomerId(CustomerId $customerId, TenantId $tenantId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.customerId = :customerId')
            ->andWhere('t.tenantId = :tenantId')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumPointsByCustomerId(CustomerId $customerId, TenantId $tenantId): int
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.points)')
            ->where('t.customerId = :customerId')
            ->andWhere('t.tenantId = :tenantId')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}
