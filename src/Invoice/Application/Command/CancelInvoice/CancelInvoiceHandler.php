<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\CancelInvoice;

use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CancelInvoice command.
 *
 * Cancels an issued invoice, changing its status to CANCELLED.
 */
#[AsMessageHandler]
final readonly class CancelInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CancelInvoiceCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);

        // Load invoice
        $invoice = $this->repository->findById($invoiceId);

        if (null === $invoice) {
            throw new \RuntimeException(sprintf('Invoice with ID %s not found', $invoiceId->toString()));
        }

        // Cancel invoice (will validate business rules)
        $invoice->cancel();

        // Save invoice (will dispatch InvoiceCancelled event)
        $this->repository->save($invoice);

        $this->logger->info('Invoice cancelled', [
            'invoice_id' => $invoiceId->toString(),
            'invoice_number' => $invoice->number()?->toString(),
            'tenant_id' => $invoice->tenantId()->toString(),
            'total' => $invoice->total()->getAmount(),
        ]);
    }
}
