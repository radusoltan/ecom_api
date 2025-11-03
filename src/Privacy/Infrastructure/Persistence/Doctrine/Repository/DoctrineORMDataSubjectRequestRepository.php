<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<DataSubjectRequestEntity>
 */
final class DoctrineORMDataSubjectRequestRepository extends ServiceEntityRepository implements DataSubjectRequestRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($registry, DataSubjectRequestEntity::class);
    }

    public function save(DataSubjectRequest $request): void
    {
        $entityManager = $this->getEntityManager();

        // Find existing entity or create new one
        $entity = $entityManager->find(DataSubjectRequestEntity::class, $request->id()->toString());

        if ($entity === null) {
            $entity = DataSubjectRequestEntity::fromDomainModel($request);
            $entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($request);
        }

        $entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($request->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(DataSubjectRequestId $id): ?DataSubjectRequest
    {
        $entity = $this->find($id->toString());

        return $entity?->toDomainModel();
    }

    public function findByCustomerId(CustomerId $customerId): array
    {
        $entities = $this->createQueryBuilder('dsr')
            ->where('dsr.customerId = :customerId')
            ->setParameter('customerId', $customerId->toString())
            ->orderBy('dsr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(DataSubjectRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByTenantId(TenantId $tenantId): array
    {
        $entities = $this->createQueryBuilder('dsr')
            ->where('dsr.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('dsr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(DataSubjectRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByStatus(RequestStatus $status, ?TenantId $tenantId = null): array
    {
        $qb = $this->createQueryBuilder('dsr')
            ->where('dsr.status = :status')
            ->setParameter('status', $status->value());

        if ($tenantId !== null) {
            $qb->andWhere('dsr.tenantId = :tenantId')
                ->setParameter('tenantId', $tenantId->toString());
        }

        $entities = $qb->orderBy('dsr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(DataSubjectRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByType(RequestType $type, ?TenantId $tenantId = null): array
    {
        $qb = $this->createQueryBuilder('dsr')
            ->where('dsr.requestType = :type')
            ->setParameter('type', $type->value());

        if ($tenantId !== null) {
            $qb->andWhere('dsr.tenantId = :tenantId')
                ->setParameter('tenantId', $tenantId->toString());
        }

        $entities = $qb->orderBy('dsr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(DataSubjectRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findOverdueRequests(?TenantId $tenantId = null): array
    {
        $now = new DateTimeImmutable();

        $qb = $this->createQueryBuilder('dsr')
            ->where('dsr.deadline < :now')
            ->andWhere('dsr.status IN (:nonFinalStatuses)')
            ->setParameter('now', $now)
            ->setParameter('nonFinalStatuses', [
                RequestStatus::pending()->value(),
                RequestStatus::underReview()->value(),
            ]);

        if ($tenantId !== null) {
            $qb->andWhere('dsr.tenantId = :tenantId')
                ->setParameter('tenantId', $tenantId->toString());
        }

        $entities = $qb->orderBy('dsr.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(DataSubjectRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function hasPendingErasureRequest(CustomerId $customerId): bool
    {
        $count = $this->createQueryBuilder('dsr')
            ->select('COUNT(dsr.id)')
            ->where('dsr.customerId = :customerId')
            ->andWhere('dsr.requestType = :type')
            ->andWhere('dsr.status IN (:nonFinalStatuses)')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('type', RequestType::erasure()->value())
            ->setParameter('nonFinalStatuses', [
                RequestStatus::pending()->value(),
                RequestStatus::underReview()->value(),
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
