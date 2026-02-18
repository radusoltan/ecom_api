<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Repository;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Model\TransactionId;
use App\Payment\Domain\Repository\TransactionRepositoryInterface;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of TransactionRepositoryInterface.
 *
 * Transactions are immutable audit logs - this repository only supports:
 * - save() for creating new transactions (insert only)
 * - read operations (findById, findByPaymentId, findByGatewayTransactionId)
 *
 * No update or delete operations are provided as transactions serve as an immutable audit trail.
 *
 * Design Patterns:
 * - Repository Pattern: Adapts infrastructure layer to domain interface
 * - Hexagonal Architecture: Repository is an adapter implementing a port
 * - Immutability: Transactions are never modified after creation
 *
 * @see TransactionRepositoryInterface
 * @see Transaction
 */
final readonly class DoctrineORMTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Save a transaction (insert only - transactions are immutable).
     *
     * This method only creates new transactions. If a transaction with the same ID
     * already exists, an exception will be thrown by Doctrine.
     *
     * Transactions do NOT record domain events (they are simple audit logs).
     *
     * @param Transaction $transaction The transaction to persist
     */
    public function save(Transaction $transaction): void
    {
        $entity = TransactionEntity::fromDomainModel($transaction);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Note: Transactions do not record domain events (immutable audit logs)
    }

    /**
     * Find transaction by ID.
     *
     * @param TransactionId $id The transaction identifier
     *
     * @return Transaction|null The transaction or null if not found
     */
    public function findById(TransactionId $id): ?Transaction
    {
        $entity = $this->entityManager->find(TransactionEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    /**
     * Get all transactions for a payment (audit log).
     *
     * Returns transactions ordered by creation date ascending (oldest first)
     * to provide a chronological audit trail.
     *
     * @param PaymentId $paymentId The payment identifier
     *
     * @return Transaction[] Array of transactions (empty if none found)
     */
    public function findByPaymentId(PaymentId $paymentId): array
    {
        $repository = $this->entityManager->getRepository(TransactionEntity::class);
        $entities = $repository->findBy(
            ['paymentId' => $paymentId->toString()],
            ['createdAt' => 'ASC'] // Chronological order for audit trail
        );

        return array_map(
            fn (TransactionEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    /**
     * Find transaction by gateway transaction ID.
     *
     * Used for webhook processing and reconciliation with payment gateway.
     *
     * @param string $gatewayTransactionId The gateway transaction identifier
     *
     * @return Transaction|null The transaction or null if not found
     */
    public function findByGatewayTransactionId(string $gatewayTransactionId): ?Transaction
    {
        $repository = $this->entityManager->getRepository(TransactionEntity::class);
        $entity = $repository->findOneBy(['gatewayTransactionId' => $gatewayTransactionId]);

        return $entity?->toDomainModel();
    }
}
