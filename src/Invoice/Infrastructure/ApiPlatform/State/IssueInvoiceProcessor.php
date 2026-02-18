<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Invoice\Application\Command\IssueInvoice\IssueInvoiceCommand;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Infrastructure\ApiPlatform\Dto\IssueInvoiceRequest;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor to issue a draft invoice (POST /api/invoices/{id}/issue).
 *
 * Issues a draft invoice by:
 * - Assigning a sequential invoice number
 * - Generating PDF document
 * - Changing status from DRAFT to ISSUED
 * - Making the invoice immutable
 *
 * Business Flow:
 * 1. Validate invoice ID from URI
 * 2. Extract tenant context
 * 3. Verify invoice exists and is in DRAFT status
 * 4. Map request to IssueInvoiceCommand
 * 5. Dispatch command via message bus
 * 6. Return updated invoice entity
 *
 * Error Scenarios:
 * - Missing invoice ID → BadRequestHttpException
 * - Missing tenant context → RuntimeException
 * - Invoice not found → NotFoundHttpException
 * - Invoice not in DRAFT status → DomainException (from handler)
 */
final readonly class IssueInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /**
     * @param IssueInvoiceRequest|null $data
     */
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
        $command = new IssueInvoiceCommand(
            invoiceId: $id,
            dueDate: $data?->dueDate,
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Retrieve and return updated invoice
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (null === $invoice) {
            throw new \RuntimeException('Invoice not found after issue');
        }

        return InvoiceEntity::fromDomainModel($invoice);
    }
}
