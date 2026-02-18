<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoicesByCustomer;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Invoices by Customer Query Handler.
 *
 * Retrieves all invoices for a specific customer.
 *
 * Business Rules:
 * - Returns all invoices across all states
 * - Results ordered by creation date descending (newest first)
 * - Multi-tenancy is enforced at database level (PostgreSQL RLS)
 *
 * @return array<int, \App\Invoice\Domain\Model\Invoice> Array of Invoice aggregates (may be empty)
 */
#[AsMessageHandler]
final readonly class GetInvoicesByCustomerHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, \App\Invoice\Domain\Model\Invoice>
     */
    public function __invoke(GetInvoicesByCustomerQuery $query): array
    {
        $customerId = CustomerId::fromString($query->customerId);

        return $this->repository->findByCustomerId($customerId);
    }
}
