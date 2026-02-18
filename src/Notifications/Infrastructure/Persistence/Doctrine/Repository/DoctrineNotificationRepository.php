<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\Doctrine\Repository;

use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Doctrine implementation of NotificationRepositoryInterface.
 *
 * Converts between domain models and Doctrine entities.
 * Dispatches domain events after persistence.
 */
final readonly class DoctrineNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function save(Notification $notification): void
    {
        $repository = $this->entityManager->getRepository(NotificationEntity::class);
        $existingEntity = $repository->find($notification->id()->toString());

        if (null === $existingEntity) {
            // INSERT: Create new entity
            $entity = NotificationEntity::fromDomainModel($notification);
            $this->entityManager->persist($entity);
        } else {
            // UPDATE: Update existing entity
            $existingEntity->updateFromDomainModel($notification);
        }

        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($notification->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(NotificationId $id, TenantId $tenantId): ?Notification
    {
        $repository = $this->entityManager->getRepository(NotificationEntity::class);

        /** @var NotificationEntity|null $entity */
        $entity = $repository->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity?->toDomainModel();
    }

    public function findByTenant(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(NotificationEntity::class);

        /** @var NotificationEntity[] $entities */
        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn (NotificationEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findPending(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(NotificationEntity::class);

        /** @var NotificationEntity[] $entities */
        $entities = $repository->findBy([
            'tenantId' => $tenantId->toString(),
            'status' => 'pending',
        ], ['createdAt' => 'ASC']);

        return array_map(
            fn (NotificationEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findFailed(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(NotificationEntity::class);

        /** @var NotificationEntity[] $entities */
        $entities = $repository->findBy([
            'tenantId' => $tenantId->toString(),
            'status' => 'failed',
        ], ['failedAt' => 'DESC']);

        return array_map(
            fn (NotificationEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }
}
