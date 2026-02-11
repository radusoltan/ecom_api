<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerSearchRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerSearchCriteria;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine Customer Search Repository.
 *
 * Implements efficient customer search with filtering and pagination.
 */
final readonly class DoctrineCustomerSearchRepository implements CustomerSearchRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @return array{customers: Customer[], total: int}
     */
    public function search(CustomerSearchCriteria $criteria): array
    {
        $qb = $this->createBaseQueryBuilder($criteria);
        $this->applyFilters($qb, $criteria);
        $this->applySorting($qb, $criteria);

        // Get total count before pagination
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT c.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply pagination
        $qb->setFirstResult($criteria->getOffset())
            ->setMaxResults($criteria->limit);

        $entities = $qb->getQuery()->getResult();

        $customers = array_map(
            fn (CustomerEntity $entity) => $entity->toDomainModel(),
            $entities
        );

        return [
            'customers' => $customers,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function countBySegment(TenantId $tenantId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c.segment', 'COUNT(c.id) as count')
            ->from(CustomerEntity::class, 'c')
            ->where('c.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->groupBy('c.segment');

        $results = $qb->getQuery()->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['segment']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * @return array{active: int, inactive: int}
     */
    public function countByStatus(TenantId $tenantId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c.isActive', 'COUNT(c.id) as count')
            ->from(CustomerEntity::class, 'c')
            ->where('c.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->groupBy('c.isActive');

        $results = $qb->getQuery()->getResult();

        $counts = ['active' => 0, 'inactive' => 0];
        foreach ($results as $result) {
            $key = $result['isActive'] ? 'active' : 'inactive';
            $counts[$key] = (int) $result['count'];
        }

        return $counts;
    }

    private function createBaseQueryBuilder(CustomerSearchCriteria $criteria): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c')
            ->from(CustomerEntity::class, 'c')
            ->where('c.tenantId = :tenantId')
            ->setParameter('tenantId', $criteria->tenantId->toString());

        return $qb;
    }

    private function applyFilters(QueryBuilder $qb, CustomerSearchCriteria $criteria): void
    {
        // Full-text search on name and email
        if (null !== $criteria->searchTerm && '' !== trim($criteria->searchTerm)) {
            $searchTerm = '%'.strtolower(trim($criteria->searchTerm)).'%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(c.firstName) LIKE :searchTerm',
                    'LOWER(c.lastName) LIKE :searchTerm',
                    'LOWER(c.email) LIKE :searchTerm',
                    'CONCAT(LOWER(c.firstName), \' \', LOWER(c.lastName)) LIKE :searchTerm'
                )
            )
            ->setParameter('searchTerm', $searchTerm);
        }

        // Filter by status (active/inactive)
        if (null !== $criteria->status) {
            $isActive = 'active' === strtolower($criteria->status);
            $qb->andWhere('c.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // Filter by segment
        if (null !== $criteria->segment) {
            $qb->andWhere('c.segment = :segment')
                ->setParameter('segment', $criteria->segment);
        }

        // Filter by registration date range
        if (null !== $criteria->registeredFrom) {
            $qb->andWhere('c.createdAt >= :registeredFrom')
                ->setParameter('registeredFrom', $criteria->registeredFrom);
        }

        if (null !== $criteria->registeredTo) {
            $qb->andWhere('c.createdAt <= :registeredTo')
                ->setParameter('registeredTo', $criteria->registeredTo);
        }

        // Filter by order count (if needed - requires join with orders table)
        if (null !== $criteria->minOrderCount || null !== $criteria->maxOrderCount || null !== $criteria->hasOrders) {
            // This requires a join with the orders table
            // For now, we'll skip this as it requires Order bounded context integration
            // TODO: Implement when Order context is available
        }
    }

    private function applySorting(QueryBuilder $qb, CustomerSearchCriteria $criteria): void
    {
        $sortField = match ($criteria->sortBy) {
            'createdAt' => 'c.createdAt',
            'updatedAt' => 'c.updatedAt',
            'firstName' => 'c.firstName',
            'lastName' => 'c.lastName',
            'email' => 'c.email',
            'loyaltyPoints' => 'c.loyaltyPoints',
            default => 'c.createdAt',
        };

        $qb->orderBy($sortField, $criteria->sortOrder);

        // Add secondary sort by ID for consistent pagination
        $qb->addOrderBy('c.id', 'ASC');
    }
}
