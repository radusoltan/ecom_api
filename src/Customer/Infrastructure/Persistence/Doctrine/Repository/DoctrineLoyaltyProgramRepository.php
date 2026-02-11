<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\LoyaltyProgramEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Doctrine Implementation of Loyalty Program Repository.
 *
 * Adapter that converts between domain models and Doctrine entities.
 * Handles persistence and dispatches domain events.
 */
final readonly class DoctrineLoyaltyProgramRepository implements LoyaltyProgramRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function save(LoyaltyProgram $program): void
    {
        $entity = $this->entityManager->find(
            LoyaltyProgramEntity::class,
            $program->id()->toString()
        );

        if (null === $entity) {
            // Create new entity
            $entity = LoyaltyProgramEntity::fromDomainModel($program);
            $this->entityManager->persist($entity);
        } else {
            // Update existing entity
            $entity->updateFromDomainModel($program);
        }

        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($program->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(LoyaltyProgramId $id, TenantId $tenantId): ?LoyaltyProgram
    {
        $entity = $this->entityManager
            ->getRepository(LoyaltyProgramEntity::class)
            ->findOneBy([
                'id' => $id->toString(),
                'tenantId' => $tenantId->toString(),
            ]);

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByTenantId(TenantId $tenantId): ?LoyaltyProgram
    {
        $entity = $this->entityManager
            ->getRepository(LoyaltyProgramEntity::class)
            ->findOneBy(['tenantId' => $tenantId->toString()]);

        return $entity ? $entity->toDomainModel() : null;
    }

    public function delete(LoyaltyProgramId $id): void
    {
        $entity = $this->entityManager->find(
            LoyaltyProgramEntity::class,
            $id->toString()
        );

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    public function exists(TenantId $tenantId): bool
    {
        $count = $this->entityManager
            ->getRepository(LoyaltyProgramEntity::class)
            ->count(['tenantId' => $tenantId->toString()]);

        return $count > 0;
    }
}
