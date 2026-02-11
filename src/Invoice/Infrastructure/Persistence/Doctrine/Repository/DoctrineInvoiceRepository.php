<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Doctrine implementation of the Invoice repository.
 *
 * Responsibilities:
 * - Persist Invoice aggregates to PostgreSQL
 * - Convert between domain models and Doctrine entities
 * - Dispatch domain events after successful persistence
 * - Provide optimized queries for invoice retrieval
 *
 * Implementation Notes:
 * - Uses updateFromDomainModel() pattern for Doctrine 3.x compatibility
 * - Eager loads invoice lines using JOIN FETCH for performance
 * - RLS (Row Level Security) handles tenant isolation at database level
 * - Events dispatched: InvoiceCreated, InvoiceIssued, InvoicePaid, InvoiceCancelled, InvoiceCredited
 */
#[AsAlias(InvoiceRepositoryInterface::class)]
final readonly class DoctrineInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function save(Invoice $invoice): void
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $invoice->id()->toString());

        if (null === $entity) {
            // Create new entity
            $entity = InvoiceEntity::fromDomainModel($invoice);
            $this->entityManager->persist($entity);
        } else {
            // Update existing entity by copying properties from domain model
            // This pattern is compatible with Doctrine 3.x
            $entity->updateFromDomainModel($invoice);
        }

        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($invoice->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(InvoiceId $id): ?Invoice
    {
        // Use DQL with JOIN FETCH to eager load lines
        $dql = 'SELECT i, l FROM '.InvoiceEntity::class.' i
                LEFT JOIN i.lines l
                WHERE i.id = :id
                ORDER BY l.position ASC';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('id', $id->toString());

        $entity = $query->getOneOrNullResult();

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByNumber(TenantId $tenantId, InvoiceNumber $invoiceNumber): ?Invoice
    {
        // Use DQL with JOIN FETCH to eager load lines
        $dql = 'SELECT i, l FROM '.InvoiceEntity::class.' i
                LEFT JOIN i.lines l
                WHERE i.tenantId = :tenantId
                AND i.invoiceNumber = :invoiceNumber
                ORDER BY l.position ASC';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('tenantId', $tenantId->toString());
        $query->setParameter('invoiceNumber', $invoiceNumber->toString());

        $entity = $query->getOneOrNullResult();

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByOrderId(OrderId $orderId): ?Invoice
    {
        // Use DQL with JOIN FETCH to eager load lines
        $dql = 'SELECT i, l FROM '.InvoiceEntity::class.' i
                LEFT JOIN i.lines l
                WHERE i.orderId = :orderId
                ORDER BY l.position ASC';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('orderId', $orderId->toString());

        $entity = $query->getOneOrNullResult();

        return $entity ? $entity->toDomainModel() : null;
    }

    /**
     * @return Invoice[]
     */
    public function findByCustomerId(CustomerId $customerId): array
    {
        // Use DQL with JOIN FETCH to eager load lines
        $dql = 'SELECT i, l FROM '.InvoiceEntity::class.' i
                LEFT JOIN i.lines l
                WHERE i.customerId = :customerId
                ORDER BY i.createdAt DESC, l.position ASC';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('customerId', $customerId->toString());

        $entities = $query->getResult();

        return array_map(
            fn (InvoiceEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    /**
     * @return Invoice[]
     */
    public function findByTenantId(TenantId $tenantId): array
    {
        // Use DQL with JOIN FETCH to eager load lines
        $dql = 'SELECT i, l FROM '.InvoiceEntity::class.' i
                LEFT JOIN i.lines l
                WHERE i.tenantId = :tenantId
                ORDER BY i.createdAt DESC, l.position ASC';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('tenantId', $tenantId->toString());

        $entities = $query->getResult();

        return array_map(
            fn (InvoiceEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    /**
     * @return Invoice[]
     */
    public function findOverdue(TenantId $tenantId): array
    {
        // Use DQL with JOIN FETCH to eager load lines
        // Find issued invoices past their due date
        $dql = 'SELECT i, l FROM '.InvoiceEntity::class.' i
                LEFT JOIN i.lines l
                WHERE i.tenantId = :tenantId
                AND i.status = :status
                AND i.dueDate < :now
                ORDER BY i.dueDate ASC, l.position ASC';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('tenantId', $tenantId->toString());
        $query->setParameter('status', 'issued');
        $query->setParameter('now', new \DateTimeImmutable());

        $entities = $query->getResult();

        return array_map(
            fn (InvoiceEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function delete(InvoiceId $id): void
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $id->toString());

        if (null === $entity) {
            throw new \RuntimeException(sprintf('Invoice with ID "%s" not found', $id->toString()));
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function nextIdentity(): InvoiceId
    {
        return InvoiceId::generate();
    }
}
