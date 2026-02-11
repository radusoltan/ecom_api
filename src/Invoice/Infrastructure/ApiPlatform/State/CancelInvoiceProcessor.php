<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Invoice\Application\Command\CancelInvoice\CancelInvoiceCommand;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor to cancel an invoice (POST /api/invoices/{id}/cancel).
 *
 * Changes invoice status from ISSUED to CANCELLED.
 *
 * Business Flow:
 * 1. Validate invoice ID from URI
 * 2. Extract tenant context
 * 3. Verify invoice exists and is in ISSUED status
 * 4. Dispatch CancelInvoiceCommand
 * 5. Return updated invoice entity
 *
 * Error Scenarios:
 * - Missing invoice ID → BadRequestHttpException
 * - Missing tenant context → RuntimeException
 * - Invoice not found → NotFoundHttpException
 * - Invoice not in ISSUED status → DomainException (from handler)
 * - Invoice is paid → DomainException (from handler)
 */
final readonly class CancelInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InvoiceEntity
    {
        $id = $uriVariables['id'] ?? null;

        if (null === $id) {
            throw new BadRequestHttpException('Invoice ID is required');
        }

        // Extract tenant context
        $tenantIdString = $context['tenant_id'] ?? null;
        if (null === $tenantIdString) {
            throw new \RuntimeException('Tenant ID not found in context');
        }

        $invoiceId = InvoiceId::fromString($id);

        // Verify invoice exists
        $invoice = $this->invoiceRepository->findById($invoiceId);
        if (null === $invoice) {
            throw new NotFoundHttpException('Invoice not found');
        }

        // Create command
        $command = new CancelInvoiceCommand(
            invoiceId: $id,
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Retrieve and return updated invoice
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (null === $invoice) {
            throw new \RuntimeException('Invoice not found after cancellation');
        }

        return InvoiceEntity::fromDomainModel($invoice);
    }
}
