<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoicesByOrder;

use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Invoice by Order Query Handler.
 *
 * Retrieves the invoice for a specific order.
 *
 * Business Rules:
 * - Each order typically has one invoice
 * - Invoice may not exist yet (returns null)
 * - Multi-tenancy is enforced at database level (PostgreSQL RLS)
 *
 * @return Invoice|null Invoice aggregate or null if not found
 */
#[AsMessageHandler]
final readonly class GetInvoicesByOrderHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetInvoicesByOrderQuery $query): ?Invoice
    {
        $orderId = OrderId::fromString($query->orderId);

        return $this->repository->findByOrderId($orderId);
    }
}
