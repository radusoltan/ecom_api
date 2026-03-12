<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\WarehouseEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DoctrineORMWarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus,
        private CacheService $cacheService,
    ) {
    }

    public function save(Warehouse $warehouse): void
    {
        $repository = $this->entityManager->getRepository(WarehouseEntity::class);

        // Check if entity already exists
        $existingEntity = $repository->find($warehouse->id()->toString());

        if ($existingEntity instanceof WarehouseEntity) {
            // Update existing entity
            $existingEntity->updateFromDomainModel($warehouse);
        } else {
            // Create new entity
            $entity = WarehouseEntity::fromDomainModel($warehouse);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($warehouse->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(WarehouseId $id): ?Warehouse
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    public function findByCodeAndTenant(WarehouseCode $code, TenantId $tenantId): ?Warehouse
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'inventory', 'warehouse_code', [
            'code' => $code->value(),
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($code, $tenantId): ?Warehouse {
                $repository = $this->entityManager->getRepository(WarehouseEntity::class);

                $entity = $repository->findOneBy([
                    'code' => $code->value(),
                    'tenantId' => $tenantId->toString(),
                ]);

                return $entity?->toDomainModel();
            },
            600,
            $this->cacheService->tenantScopedTags($tenant, 'warehouses')
        );
    }

    public function findByTenant(TenantId $tenantId): array
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'inventory', 'warehouses', [
            'scope' => 'all',
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId): array {
                $repository = $this->entityManager->getRepository(WarehouseEntity::class);

                $entities = $repository->findBy(
                    ['tenantId' => $tenantId->toString()],
                    ['priority' => 'ASC', 'name' => 'ASC']
                );

                return array_map(
                    fn (WarehouseEntity $entity): Warehouse => $entity->toDomainModel(),
                    $entities
                );
            },
            600,
            $this->cacheService->tenantScopedTags($tenant, 'warehouses')
        );
    }

    public function findActiveByTenant(TenantId $tenantId): array
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'inventory', 'active_warehouses', [
            'scope' => 'active',
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId): array {
                $repository = $this->entityManager->getRepository(WarehouseEntity::class);

                $entities = $repository->findBy(
                    [
                        'tenantId' => $tenantId->toString(),
                        'isActive' => true,
                    ],
                    ['priority' => 'ASC', 'name' => 'ASC']
                );

                return array_map(
                    fn (WarehouseEntity $entity): Warehouse => $entity->toDomainModel(),
                    $entities
                );
            },
            600,
            $this->cacheService->tenantScopedTags($tenant, 'warehouses')
        );
    }

    public function codeExistsForTenant(WarehouseCode $code, TenantId $tenantId): bool
    {
        $repository = $this->entityManager->getRepository(WarehouseEntity::class);

        $count = $repository->count([
            'code' => $code->value(),
            'tenantId' => $tenantId->toString(),
        ]);

        return $count > 0;
    }
}
