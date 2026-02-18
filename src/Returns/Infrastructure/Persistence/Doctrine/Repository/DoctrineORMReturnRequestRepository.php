<?php

declare(strict_types=1);

namespace App\Returns\Infrastructure\Persistence\Doctrine\Repository;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Returns\Infrastructure\Persistence\Doctrine\Entity\ReturnRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Doctrine ORM implementation of ReturnRequestRepositoryInterface.
 *
 * Responsibilities:
 * - Convert between domain models and Doctrine entities
 * - Persist to database
 * - Dispatch domain events after persistence
 */
final class DoctrineORMReturnRequestRepository implements ReturnRequestRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function save(ReturnRequest $returnRequest): void
    {
        $entity = $this->entityManager->find(ReturnRequestEntity::class, $returnRequest->id()->toString());

        if (null === $entity) {
            // New return request - create entity
            $entity = ReturnRequestEntity::fromDomainModel($returnRequest);
            $this->entityManager->persist($entity);
        } else {
            // Existing return request - update entity in-place (Doctrine 3.x pattern)
            $entity->updateFromDomainModel($returnRequest);
        }

        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($returnRequest->getRecordedEvents() as $event) {
            $this->eventBus->dispatch($event);
        }

        $returnRequest->clearRecordedEvents();
    }

    public function findById(ReturnRequestId $id): ?ReturnRequest
    {
        $entity = $this->entityManager->find(ReturnRequestEntity::class, $id->toString());

        if (null === $entity) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findByOrderId(OrderId $orderId): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('r')
            ->from(ReturnRequestEntity::class, 'r')
            ->where('r.orderId = :orderId')
            ->setParameter('orderId', $orderId->toString())
            ->orderBy('r.createdAt', 'DESC');

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (ReturnRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByTenantId(TenantId $tenantId): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('r')
            ->from(ReturnRequestEntity::class, 'r')
            ->where('r.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('r.createdAt', 'DESC');

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (ReturnRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByStatus(string $status, TenantId $tenantId): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('r')
            ->from(ReturnRequestEntity::class, 'r')
            ->where('r.tenantId = :tenantId')
            ->andWhere('r.status = :status')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('status', $status)
            ->orderBy('r.createdAt', 'DESC');

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (ReturnRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }
}
