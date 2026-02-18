<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Repository;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Infrastructure\Persistence\Doctrine\Entity\TenantEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DoctrineORMTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function save(Tenant $tenant): void
    {
        $repository = $this->entityManager->getRepository(TenantEntity::class);

        // Check if entity already exists
        $existingEntity = $repository->find($tenant->id()->toString());

        if ($existingEntity instanceof TenantEntity) {
            // Update existing entity
            $existingEntity->updateFromDomain($tenant);
        } else {
            // Create new entity
            $entity = TenantEntity::fromDomain($tenant);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($tenant->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function delete(Tenant $tenant): void
    {
        $entity = $this->entityManager->find(TenantEntity::class, $tenant->id()->toString());

        if ($entity instanceof TenantEntity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }

        // Dispatch domain events after successful deletion
        foreach ($tenant->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(TenantId $id): ?Tenant
    {
        $entity = $this->entityManager->find(TenantEntity::class, $id->toString());

        return $entity?->toDomain();
    }

    public function findByOwnerEmail(Email $email): ?Tenant
    {
        $repository = $this->entityManager->getRepository(TenantEntity::class);

        $entity = $repository->findOneBy(['ownerEmail' => $email->value()]);

        return $entity?->toDomain();
    }

    public function findAll(): array
    {
        $repository = $this->entityManager->getRepository(TenantEntity::class);

        $entities = $repository->findAll();

        return array_map(
            fn (TenantEntity $entity): Tenant => $entity->toDomain(),
            $entities
        );
    }
}
