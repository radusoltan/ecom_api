<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\DeletionRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DoctrineDeletionRequestRepository implements DeletionRequestRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus
    ) {
    }

    public function save(DeletionRequest $request): void
    {
        $entity = $this->entityManager
            ->getRepository(DeletionRequestEntity::class)
            ->find($request->id()->toString());

        if (null === $entity) {
            // New deletion request
            $entity = DeletionRequestEntity::fromDomainModel($request);
            $this->entityManager->persist($entity);
        } else {
            // Update existing
            $entity->updateFromDomainModel($request);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($request->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(DeletionRequestId $id, TenantId $tenantId): ?DeletionRequest
    {
        $entity = $this->entityManager
            ->getRepository(DeletionRequestEntity::class)
            ->findOneBy([
                'id' => $id->toString(),
                'tenantId' => $tenantId->toString(),
            ]);

        return $entity?->toDomainModel();
    }

    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): ?DeletionRequest
    {
        $entity = $this->entityManager
            ->getRepository(DeletionRequestEntity::class)
            ->findOneBy([
                'customerId' => $customerId->toString(),
                'tenantId' => $tenantId->toString(),
            ], ['createdAt' => 'DESC']); // Get most recent

        return $entity?->toDomainModel();
    }

    public function findPendingByCustomerId(CustomerId $customerId, TenantId $tenantId): ?DeletionRequest
    {
        $entity = $this->entityManager
            ->getRepository(DeletionRequestEntity::class)
            ->findOneBy([
                'customerId' => $customerId->toString(),
                'tenantId' => $tenantId->toString(),
                'status' => DeletionStatus::PENDING->value,
            ]);

        return $entity?->toDomainModel();
    }

    public function findReadyForExecution(): array
    {
        $qb = $this->entityManager
            ->getRepository(DeletionRequestEntity::class)
            ->createQueryBuilder('dr');

        $entities = $qb
            ->where('dr.status = :status')
            ->andWhere('dr.scheduledFor <= :now')
            ->setParameter('status', DeletionStatus::CONFIRMED->value)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        return array_map(
            fn (DeletionRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }
}
