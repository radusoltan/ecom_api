<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\MarkInvoicePaid;

use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for MarkInvoicePaid command.
 *
 * Marks an issued invoice as paid and records payment date.
 */
#[AsMessageHandler]
final readonly class MarkInvoicePaidHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MarkInvoicePaidCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);

        // Load invoice
        $invoice = $this->repository->findById($invoiceId);

        if (null === $invoice) {
            throw new \RuntimeException(sprintf('Invoice with ID %s not found', $invoiceId->toString()));
        }

        // Parse paid date
        $paidDate = new \DateTimeImmutable($command->paidDate);

        // Mark as paid (will validate business rules)
        $invoice->markAsPaid($paidDate);

        // Save invoice (will dispatch InvoicePaid event)
        $this->repository->save($invoice);

        $this->logger->info('Invoice marked as paid', [
            'invoice_id' => $invoiceId->toString(),
            'invoice_number' => $invoice->number()?->toString(),
            'tenant_id' => $invoice->tenantId()->toString(),
            'paid_date' => $paidDate->format('Y-m-d'),
            'total' => $invoice->total()->getAmount(),
        ]);
    }
}
