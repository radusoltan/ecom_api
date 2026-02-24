<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\EventListener;

use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Shared\Infrastructure\Encryption\BlindIndexService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Automatically generates blind indexes for CustomerEntity email field.
 *
 * This ensures blind indexes are always set when a CustomerEntity is persisted or updated,
 * regardless of whether the entity was created via the domain repository or directly.
 */
#[AsEntityListener(event: Events::prePersist, entity: CustomerEntity::class)]
#[AsEntityListener(event: Events::preUpdate, entity: CustomerEntity::class)]
final readonly class CustomerBlindIndexListener
{
    public function __construct(
        private BlindIndexService $blindIndexService,
    ) {
    }

    public function prePersist(CustomerEntity $entity): void
    {
        $this->updateBlindIndexes($entity);
    }

    public function preUpdate(CustomerEntity $entity): void
    {
        $this->updateBlindIndexes($entity);
    }

    private function updateBlindIndexes(CustomerEntity $entity): void
    {
        // Generate email blind index if missing
        if (null === $entity->getEmailBlindIndex() && '' !== $entity->getEmail()) {
            $entity->setEmailBlindIndex(
                $this->blindIndexService->generate($entity->getEmail())
            );
        }
    }
}
