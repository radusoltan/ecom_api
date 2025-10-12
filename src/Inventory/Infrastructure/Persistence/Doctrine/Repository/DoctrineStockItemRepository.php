<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<StockItemEntity>
 */
final class DoctrineStockItemRepository extends ServiceEntityRepository implements StockItemRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($registry, StockItemEntity::class);
    }

    public function save(StockItem $stockItem): void
    {
        // Try to find existing entity using raw SQL to avoid type conversion issues
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.id = :id')
            ->setParameter('id', $stockItem->id()->toString());

        $existingEntity = $qb->getQuery()->getOneOrNullResult();

        if ($existingEntity instanceof StockItemEntity) {
            // Update existing entity
            $existingEntity->updateFromDomainModel($stockItem);
        } else {
            // Create new entity
            $entity = StockItemEntity::fromDomainModel($stockItem);
            $this->getEntityManager()->persist($entity);
        }

        $this->getEntityManager()->flush();

        // Dispatch domain events
        foreach ($stockItem->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(StockItemId $id): ?StockItem
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.id = :id')
            ->setParameter('id', $id->toString());

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findByProductAndWarehouse(
        ProductId $productId,
        WarehouseId $warehouseId,
        TenantId $tenantId
    ): ?StockItem {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.productId = :productId')
            ->andWhere('s.warehouseId = :warehouseId')
            ->andWhere('s.tenantId = :tenantId')
            ->setParameter('productId', $productId->toString())
            ->setParameter('warehouseId', $warehouseId->toString())
            ->setParameter('tenantId', $tenantId->toString());

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findByProduct(ProductId $productId, TenantId $tenantId): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.productId = :productId')
            ->andWhere('s.tenantId = :tenantId')
            ->setParameter('productId', $productId->toString())
            ->setParameter('tenantId', $tenantId->toString());

        $entities = $qb->getQuery()->getResult();

        return array_map(fn($entity) => $entity->toDomainModel(), $entities);
    }

    public function findByWarehouse(WarehouseId $warehouseId, TenantId $tenantId): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.warehouseId = :warehouseId')
            ->andWhere('s.tenantId = :tenantId')
            ->setParameter('warehouseId', $warehouseId->toString())
            ->setParameter('tenantId', $tenantId->toString());

        $entities = $qb->getQuery()->getResult();

        return array_map(fn($entity) => $entity->toDomainModel(), $entities);
    }

    public function findLowStockItems(TenantId $tenantId): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.tenantId = :tenantId')
            ->andWhere('(s.onHand - s.reserved - s.allocated) <= s.lowStockThreshold')
            ->setParameter('tenantId', $tenantId->toString());

        $entities = $qb->getQuery()->getResult();

        return array_map(fn($entity) => $entity->toDomainModel(), $entities);
    }

    public function findByTenant(TenantId $tenantId): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString());

        $entities = $qb->getQuery()->getResult();

        return array_map(fn($entity) => $entity->toDomainModel(), $entities);
    }

    public function delete(StockItem $stockItem): void
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.id = :id')
            ->setParameter('id', $stockItem->id()->toString());

        $entity = $qb->getQuery()->getOneOrNullResult();

        if ($entity !== null) {
            $this->getEntityManager()->remove($entity);
            $this->getEntityManager()->flush();
        }
    }
}
