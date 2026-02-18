<?php

declare(strict_types=1);

namespace App\AuditLog\Infrastructure\Persistence\Doctrine\Repository;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\AuditLogId;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\AuditLog\Infrastructure\Persistence\Doctrine\Entity\AuditLogEntryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLogEntryEntity>
 */
final class DoctrineAuditLogRepository extends ServiceEntityRepository implements AuditLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLogEntryEntity::class);
    }

    public function save(AuditLogEntry $entry): void
    {
        $entity = AuditLogEntryEntity::fromDomainModel($entry);

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function findById(AuditLogId $id, TenantId $tenantId): ?AuditLogEntry
    {
        $entity = $this->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByTenant(TenantId $tenantId, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('a.occurredAt', 'DESC');

        // Apply filters
        if (isset($filters['userId'])) {
            /** @var UserId $userId */
            $userId = $filters['userId'];
            $qb->andWhere('a.userId = :userId')
               ->setParameter('userId', $userId->toString());
        }

        if (isset($filters['actionType'])) {
            /** @var ActionType $actionType */
            $actionType = $filters['actionType'];
            $qb->andWhere('a.actionType = :actionType')
               ->setParameter('actionType', $actionType->toString());
        }

        if (isset($filters['resourceType'])) {
            /** @var ResourceType $resourceType */
            $resourceType = $filters['resourceType'];
            $qb->andWhere('a.resourceType = :resourceType')
               ->setParameter('resourceType', $resourceType->toString());
        }

        if (isset($filters['resourceId'])) {
            $qb->andWhere('a.resourceId = :resourceId')
               ->setParameter('resourceId', $filters['resourceId']);
        }

        if (isset($filters['fromDate'])) {
            /** @var \DateTimeImmutable $fromDate */
            $fromDate = $filters['fromDate'];
            $qb->andWhere('a.occurredAt >= :fromDate')
               ->setParameter('fromDate', $fromDate);
        }

        if (isset($filters['toDate'])) {
            /** @var \DateTimeImmutable $toDate */
            $toDate = $filters['toDate'];
            $qb->andWhere('a.occurredAt <= :toDate')
               ->setParameter('toDate', $toDate);
        }

        // Apply pagination
        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (isset($filters['offset'])) {
            $qb->setFirstResult($filters['offset']);
        }

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (AuditLogEntryEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByUser(UserId $userId, TenantId $tenantId, int $limit = 100, int $offset = 0): array
    {
        return $this->findByTenant($tenantId, [
            'userId' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function findByResource(
        ResourceType $resourceType,
        string $resourceId,
        TenantId $tenantId,
        int $limit = 100,
        int $offset = 0,
    ): array {
        return $this->findByTenant($tenantId, [
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function countEntries(TenantId $tenantId, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString());

        // Apply same filters as findByTenant (without pagination)
        if (isset($filters['userId'])) {
            /** @var UserId $userId */
            $userId = $filters['userId'];
            $qb->andWhere('a.userId = :userId')
               ->setParameter('userId', $userId->toString());
        }

        if (isset($filters['actionType'])) {
            /** @var ActionType $actionType */
            $actionType = $filters['actionType'];
            $qb->andWhere('a.actionType = :actionType')
               ->setParameter('actionType', $actionType->toString());
        }

        if (isset($filters['resourceType'])) {
            /** @var ResourceType $resourceType */
            $resourceType = $filters['resourceType'];
            $qb->andWhere('a.resourceType = :resourceType')
               ->setParameter('resourceType', $resourceType->toString());
        }

        if (isset($filters['resourceId'])) {
            $qb->andWhere('a.resourceId = :resourceId')
               ->setParameter('resourceId', $filters['resourceId']);
        }

        if (isset($filters['fromDate'])) {
            /** @var \DateTimeImmutable $fromDate */
            $fromDate = $filters['fromDate'];
            $qb->andWhere('a.occurredAt >= :fromDate')
               ->setParameter('fromDate', $fromDate);
        }

        if (isset($filters['toDate'])) {
            /** @var \DateTimeImmutable $toDate */
            $toDate = $filters['toDate'];
            $qb->andWhere('a.occurredAt <= :toDate')
               ->setParameter('toDate', $toDate);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
