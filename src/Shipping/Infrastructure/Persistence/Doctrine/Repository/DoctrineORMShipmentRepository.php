<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use App\Shipping\Infrastructure\Persistence\Doctrine\Entity\ShipmentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMShipmentRepository implements ShipmentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function save(Shipment $shipment): void
    {
        $entity = $this->entityManager->find(ShipmentEntity::class, $shipment->id()->toString());

        if (null === $entity) {
            $entity = ShipmentEntity::fromDomainModel($shipment);
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($shipment);
        }

        $this->entityManager->flush();

        foreach ($shipment->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(ShipmentId $id): ?Shipment
    {
        $entity = $this->entityManager->find(ShipmentEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    public function findByIdAndTenant(ShipmentId $id, TenantId $tenantId): ?Shipment
    {
        $entity = $this->entityManager->getRepository(ShipmentEntity::class)->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity?->toDomainModel();
    }

    public function findByOrderId(string $orderId, TenantId $tenantId): ?Shipment
    {
        $entity = $this->entityManager->getRepository(ShipmentEntity::class)->findOneBy([
            'orderId' => $orderId,
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity?->toDomainModel();
    }

    /** @return Shipment[] */
    public function findAllByTenant(TenantId $tenantId): array
    {
        $entities = $this->entityManager->getRepository(ShipmentEntity::class)->findBy([
            'tenantId' => $tenantId->toString(),
        ]);

        return array_map(fn (ShipmentEntity $entity) => $entity->toDomainModel(), $entities);
    }
}
