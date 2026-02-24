<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\EventListener;

use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use App\Shared\Infrastructure\Encryption\BlindIndexService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Automatically generates blind indexes for OrderEntity customer email field.
 *
 * This ensures blind indexes are always set when an OrderEntity is persisted or updated,
 * regardless of whether the entity was created via the domain repository or directly.
 */
#[AsEntityListener(event: Events::prePersist, entity: OrderEntity::class)]
#[AsEntityListener(event: Events::preUpdate, entity: OrderEntity::class)]
final readonly class OrderBlindIndexListener
{
    public function __construct(
        private BlindIndexService $blindIndexService,
    ) {
    }

    public function prePersist(OrderEntity $entity): void
    {
        $this->updateBlindIndexes($entity);
    }

    public function preUpdate(OrderEntity $entity): void
    {
        $this->updateBlindIndexes($entity);
    }

    private function updateBlindIndexes(OrderEntity $entity): void
    {
        // Generate customer email blind index if missing
        if (null === $entity->getCustomerEmailBlindIndex() && '' !== $entity->getCustomerEmail()) {
            $entity->setCustomerEmailBlindIndex(
                $this->blindIndexService->generate($entity->getCustomerEmail())
            );
        }
    }
}
