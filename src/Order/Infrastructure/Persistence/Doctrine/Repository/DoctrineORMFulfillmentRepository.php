<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Repository;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Order\Infrastructure\Persistence\Doctrine\Entity\FulfillmentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Doctrine ORM Fulfillment Repository
 *
 * Implements persistence for Fulfillment aggregate using Doctrine ORM.
 */
final readonly class DoctrineORMFulfillmentRepository implements FulfillmentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function save(Fulfillment $fulfillment): void
    {
        $repository = $this->entityManager->getRepository(FulfillmentEntity::class);
        $entity = $repository->find($fulfillment->id()->toString());

        if ($entity === null) {
            // Create new entity
            $entity = FulfillmentEntity::fromDomainModel($fulfillment);
            $this->entityManager->persist($entity);
        } else {
            // Update existing entity
            $entity->updateFromDomainModel($fulfillment);
        }

        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($fulfillment->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(FulfillmentId $id): ?Fulfillment
    {
        $repository = $this->entityManager->getRepository(FulfillmentEntity::class);
        $entity = $repository->find($id->toString());

        return $entity?->toDomainModel();
    }

    public function findByOrderId(OrderId $orderId): ?Fulfillment
    {
        $repository = $this->entityManager->getRepository(FulfillmentEntity::class);
        $entity = $repository->findOneBy(['orderId' => $orderId->toString()]);

        return $entity?->toDomainModel();
    }

    public function findByTenant(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(FulfillmentEntity::class);
        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn(FulfillmentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByStatus(FulfillmentStatus $status, TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(FulfillmentEntity::class);
        $entities = $repository->findBy(
            [
                'tenantId' => $tenantId->toString(),
                'status' => $status->value(),
            ],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn(FulfillmentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByWarehouse(WarehouseId $warehouseId, TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(FulfillmentEntity::class);
        $entities = $repository->findBy(
            [
                'warehouseId' => $warehouseId->toString(),
                'tenantId' => $tenantId->toString(),
            ],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn(FulfillmentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findInProgress(TenantId $tenantId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('f')
            ->from(FulfillmentEntity::class, 'f')
            ->where('f.tenantId = :tenantId')
            ->andWhere($qb->expr()->notIn('f.status', [
                ':delivered',
                ':cancelled',
                ':failed',
            ]))
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('delivered', FulfillmentStatus::delivered()->value())
            ->setParameter('cancelled', FulfillmentStatus::cancelled()->value())
            ->setParameter('failed', FulfillmentStatus::failed()->value())
            ->orderBy('f.createdAt', 'DESC');

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn(FulfillmentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }
}
