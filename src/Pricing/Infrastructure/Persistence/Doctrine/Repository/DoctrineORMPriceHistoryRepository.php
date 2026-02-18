<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PriceHistoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of PriceHistoryRepositoryInterface.
 *
 * This adapter converts between PriceChange value objects and PriceHistoryEntity.
 */
final readonly class DoctrineORMPriceHistoryRepository implements PriceHistoryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(PriceChange $priceChange, TenantId $tenantId, ?UserId $changedBy = null): void
    {
        $entity = PriceHistoryEntity::fromPriceChange($priceChange, $tenantId, $changedBy);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findByProductId(
        ProductId $productId,
        TenantId $tenantId,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ph')
            ->from(PriceHistoryEntity::class, 'ph')
            ->where('ph.productId = :productId')
            ->andWhere('ph.tenantId = :tenantId')
            ->orderBy('ph.changedAt', 'DESC')
            ->setParameter('productId', $productId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (PriceHistoryEntity $entity) => $entity->toPriceChange(),
            $entities
        );
    }

    public function findByTenant(TenantId $tenantId, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ph')
            ->from(PriceHistoryEntity::class, 'ph')
            ->where('ph.tenantId = :tenantId')
            ->orderBy('ph.changedAt', 'DESC')
            ->setParameter('tenantId', $tenantId->toString())
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (PriceHistoryEntity $entity) => $entity->toPriceChange(),
            $entities
        );
    }

    public function findByDateRange(
        TenantId $tenantId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ph')
            ->from(PriceHistoryEntity::class, 'ph')
            ->where('ph.tenantId = :tenantId')
            ->andWhere('ph.changedAt >= :from')
            ->andWhere('ph.changedAt <= :to')
            ->orderBy('ph.changedAt', 'DESC')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (PriceHistoryEntity $entity) => $entity->toPriceChange(),
            $entities
        );
    }

    public function getLatestForProduct(ProductId $productId, TenantId $tenantId): ?PriceChange
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ph')
            ->from(PriceHistoryEntity::class, 'ph')
            ->where('ph.productId = :productId')
            ->andWhere('ph.tenantId = :tenantId')
            ->orderBy('ph.changedAt', 'DESC')
            ->setParameter('productId', $productId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity?->toPriceChange();
    }

    public function getLowestPriceInLastDays(ProductId $productId, TenantId $tenantId, int $days = 30): ?PriceChange
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ph')
            ->from(PriceHistoryEntity::class, 'ph')
            ->where('ph.productId = :productId')
            ->andWhere('ph.tenantId = :tenantId')
            ->andWhere('ph.changedAt >= :since')
            ->andWhere('ph.newPriceAmount IS NOT NULL') // Only consider records with new price
            ->orderBy('ph.newPriceAmount', 'ASC') // Lowest price first
            ->addOrderBy('ph.changedAt', 'DESC') // Most recent if tied
            ->setParameter('productId', $productId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('since', $since)
            ->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity?->toPriceChange();
    }

    public function countByProductId(ProductId $productId, TenantId $tenantId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(ph.id)')
            ->from(PriceHistoryEntity::class, 'ph')
            ->where('ph.productId = :productId')
            ->andWhere('ph.tenantId = :tenantId')
            ->setParameter('productId', $productId->toString())
            ->setParameter('tenantId', $tenantId->toString());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countByTenant(TenantId $tenantId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(ph.id)')
            ->from(PriceHistoryEntity::class, 'ph')
            ->where('ph.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $beforeDate): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(PriceHistoryEntity::class, 'ph')
            ->where('ph.changedAt < :beforeDate')
            ->setParameter('beforeDate', $beforeDate);

        return $qb->getQuery()->execute();
    }
}
