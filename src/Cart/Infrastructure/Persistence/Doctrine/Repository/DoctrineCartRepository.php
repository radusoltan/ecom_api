<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Persistence\Doctrine\Repository;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Cart\Infrastructure\Persistence\Doctrine\Entity\CartEntity;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineCartRepository implements CartRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function save(Cart $cart): void
    {
        $entity = $this->entityManager->find(CartEntity::class, $cart->id()->toString());

        if (null === $entity) {
            // Create new entity
            $entity = CartEntity::fromDomainModel($cart);
            $this->entityManager->persist($entity);
        } else {
            // Update existing entity
            $entity->updateFromDomainModel($cart);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($cart->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(CartId $id): ?Cart
    {
        $entity = $this->entityManager->find(CartEntity::class, $id->toString());

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): ?Cart
    {
        $entity = $this->entityManager->getRepository(CartEntity::class)->findOneBy([
            'customerId' => $customerId->toString(),
            'tenantId' => $tenantId->toString(),
            'status' => 'active', // Only return active carts
        ]);

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findBySessionId(SessionId $sessionId, TenantId $tenantId): ?Cart
    {
        $entity = $this->entityManager->getRepository(CartEntity::class)->findOneBy([
            'sessionId' => $sessionId->toString(),
            'tenantId' => $tenantId->toString(),
            'status' => 'active', // Only return active carts
        ]);

        return $entity ? $entity->toDomainModel() : null;
    }

    public function remove(Cart $cart): void
    {
        $entity = $this->entityManager->find(CartEntity::class, $cart->id()->toString());

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    /**
     * Find carts that haven't been updated since the given date.
     *
     * @return Cart[]
     */
    public function findExpired(\DateTimeImmutable $before): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('c')
            ->from(CartEntity::class, 'c')
            ->where('c.updatedAt < :before')
            ->andWhere('c.status = :status')
            ->setParameter('before', $before)
            ->setParameter('status', 'active')
            ->orderBy('c.updatedAt', 'ASC')
            ->setMaxResults(100); // Process in batches

        /** @var CartEntity[] $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (CartEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    /**
     * Find active carts by tenant and customer email.
     *
     * Note: Since we don't store email in cart, this method is a placeholder
     * In practice, cart clearing should be done via session ID or customer ID
     *
     * @return Cart[]
     */
    public function findActiveByTenantAndEmail(TenantId $tenantId, string $customerEmail): array
    {
        // For now, return empty array
        // In production, we would need to:
        // 1. Join with customer table to match email
        // 2. Or pass session ID through order metadata
        // 3. Or have frontend explicitly clear cart after order

        return [];
    }

    /**
     * Find abandoned carts ready for email notification.
     *
     * @return CartId[]
     */
    public function findAbandonedCartsForEmail(\DateTimeImmutable $abandonedBefore, int $limit = 100): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('c.id')
            ->from(CartEntity::class, 'c')
            ->where('c.updatedAt < :abandonedBefore')
            ->andWhere('c.status = :status')
            ->andWhere('c.abandonmentEmailSent = :emailSent')
            ->andWhere('SIZE(c.items) > 0') // Only carts with items
            ->setParameter('abandonedBefore', $abandonedBefore)
            ->setParameter('status', 'active')
            ->setParameter('emailSent', false)
            ->orderBy('c.updatedAt', 'ASC') // Process oldest first
            ->setMaxResults($limit);

        /** @var array<int, array{id: string}> $results */
        $results = $qb->getQuery()->getResult();

        return array_map(
            fn (array $result) => CartId::fromString($result['id']),
            $results
        );
    }

    /**
     * Mark cart as having received abandonment email.
     */
    public function markAbandonmentEmailSent(CartId $cartId): void
    {
        $entity = $this->entityManager->find(CartEntity::class, $cartId->toString());

        if (null !== $entity) {
            $entity->markAbandonmentEmailSent();
            $this->entityManager->flush();
        }
    }

    public function markAsConverted(CartId $cartId): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement(
            'UPDATE carts SET status = :status, updated_at = :updatedAt WHERE id = :id',
            [
                'status' => 'converted',
                'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $cartId->toString(),
            ]
        );
    }
}
