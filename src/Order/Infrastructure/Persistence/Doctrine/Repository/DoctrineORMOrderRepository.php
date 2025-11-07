<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Repository;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function save(Order $order): void
    {
        $entity = $this->entityManager->find(OrderEntity::class, $order->id()->toString());

        if (null === $entity) {
            // Create new entity
            $entity = OrderEntity::fromDomainModel($order);
            $this->entityManager->persist($entity);
        } else {
            // Update existing entity by copying properties from domain model
            $entity->updateFromDomainModel($order);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($order->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(OrderId $id): ?Order
    {
        $entity = $this->entityManager->find(OrderEntity::class, $id->toString());

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByIdAndTenant(OrderId $id, TenantId $tenantId): ?Order
    {
        $entity = $this->entityManager->getRepository(OrderEntity::class)->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByCustomerEmail(string $email, TenantId $tenantId): array
    {
        $entities = $this->entityManager->getRepository(OrderEntity::class)->findBy([
            'customerEmail' => $email,
            'tenantId' => $tenantId->toString(),
        ], ['createdAt' => 'DESC']);

        return array_map(
            fn (OrderEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findAllByTenant(TenantId $tenantId): array
    {
        $entities = $this->entityManager->getRepository(OrderEntity::class)->findBy([
            'tenantId' => $tenantId->toString(),
        ], ['createdAt' => 'DESC']);

        return array_map(
            fn (OrderEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }
}
