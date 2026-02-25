<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine\EventListener;

use App\Shared\Infrastructure\Encryption\BlindIndexService;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Automatically generates blind indexes for UserEntity email and username fields.
 *
 * This ensures blind indexes are always set when a UserEntity is persisted or updated,
 * regardless of whether the entity was created via the domain repository or directly.
 */
#[AsEntityListener(event: Events::prePersist, entity: UserEntity::class)]
#[AsEntityListener(event: Events::preUpdate, entity: UserEntity::class)]
final readonly class UserBlindIndexListener
{
    public function __construct(
        private BlindIndexService $blindIndexService,
    ) {
    }

    public function prePersist(UserEntity $entity): void
    {
        $this->updateBlindIndexes($entity);
    }

    public function preUpdate(UserEntity $entity): void
    {
        $this->updateBlindIndexes($entity);
    }

    private function updateBlindIndexes(UserEntity $entity): void
    {
        // Generate email blind index if missing
        if (null === $entity->getEmailBlindIndex() && '' !== $entity->getEmail()) {
            $entity->setEmailBlindIndex(
                $this->blindIndexService->generate($entity->getEmail())
            );
        }

        // Generate username blind index if missing
        if (null === $entity->getUsernameBlindIndex() && '' !== $entity->getUsername()) {
            $entity->setUsernameBlindIndex(
                $this->blindIndexService->generate($entity->getUsername())
            );
        }
    }
}
