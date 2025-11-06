<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use App\Wishlist\Domain\ValueObject\WishlistId;
use App\Wishlist\Infrastructure\Persistence\Doctrine\Entity\WishlistEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DoctrineWishlistRepository implements WishlistRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus
    ) {}

    public function save(Wishlist $wishlist): void
    {
        $entity = $this->entityManager->find(WishlistEntity::class, $wishlist->id()->toString());

        if ($entity === null) {
            $entity = WishlistEntity::fromDomainModel($wishlist);
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($wishlist);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($wishlist->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(WishlistId $id): ?Wishlist
    {
        $entity = $this->entityManager->find(WishlistEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    public function findByCustomerId(string $customerId, TenantId $tenantId): ?Wishlist
    {
        $entity = $this->entityManager->getRepository(WishlistEntity::class)
            ->findOneBy([
                'customerId' => $customerId,
                'tenantId' => $tenantId->toString(),
            ]);

        return $entity?->toDomainModel();
    }

    public function delete(Wishlist $wishlist): void
    {
        $entity = $this->entityManager->find(WishlistEntity::class, $wishlist->id()->toString());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
