<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoice;

use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Invoice Query Handler.
 *
 * Retrieves a single invoice by its unique identifier.
 *
 * Business Rules:
 * - Invoice must exist in the repository
 * - Multi-tenancy is enforced at database level (PostgreSQL RLS)
 *
 * @return Invoice|null Invoice aggregate or null if not found
 */
#[AsMessageHandler]
final readonly class GetInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository
    ) {
    }

    public function __invoke(GetInvoiceQuery $query): ?Invoice
    {
        $invoiceId = InvoiceId::fromString($query->invoiceId);

        return $this->repository->findById($invoiceId);
    }
}
