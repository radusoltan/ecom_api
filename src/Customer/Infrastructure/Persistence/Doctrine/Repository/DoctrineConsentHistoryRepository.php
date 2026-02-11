<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\ConsentHistoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Doctrine implementation of ConsentHistoryRepository.
 *
 * Handles persistence of consent history records with multi-tenant isolation via PostgreSQL RLS.
 *
 * @extends ServiceEntityRepository<ConsentHistoryEntity>
 */
final class DoctrineConsentHistoryRepository extends ServiceEntityRepository implements ConsentHistoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsentHistoryEntity::class);
    }

    public function save(ConsentHistory $history): void
    {
        $entity = ConsentHistoryEntity::fromDomainModel($history);

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    /**
     * @return array<ConsentHistory>
     */
    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): array
    {
        $entities = $this->createQueryBuilder('ch')
            ->where('ch.customerId = :customerId')
            ->andWhere('ch.tenantId = :tenantId')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('ch.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn (ConsentHistoryEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    /**
     * @return array<ConsentHistory>
     */
    public function findByCustomerIdPaginated(
        CustomerId $customerId,
        TenantId $tenantId,
        int $page = 1,
        int $limit = 20
    ): array {
        if ($page < 1) {
            $page = 1;
        }

        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }

        $offset = ($page - 1) * $limit;

        $entities = $this->createQueryBuilder('ch')
            ->where('ch.customerId = :customerId')
            ->andWhere('ch.tenantId = :tenantId')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('ch.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            fn (ConsentHistoryEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }
}
