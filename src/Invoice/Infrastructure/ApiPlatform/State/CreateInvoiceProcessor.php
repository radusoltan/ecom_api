<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceCommand;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceLineDto;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Infrastructure\ApiPlatform\Dto\CreateInvoiceRequest;
use App\Invoice\Infrastructure\ApiPlatform\Dto\InvoiceLineRequest;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor to create a draft invoice (POST /api/invoices).
 *
 * Creates a new invoice in DRAFT status that can be modified before being issued.
 *
 * Business Flow:
 * 1. Validate request data
 * 2. Extract tenant context
 * 3. Generate new invoice ID
 * 4. Map request to GenerateInvoiceCommand
 * 5. Dispatch command via message bus
 * 6. Return created invoice entity
 *
 * Error Scenarios:
 * - Missing tenant context → RuntimeException
 * - Invalid billing address → BadRequestHttpException
 * - Empty lines array → BadRequestHttpException
 * - Domain validation errors → DomainException (from handler)
 */
final readonly class CreateInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /**
     * @param CreateInvoiceRequest $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InvoiceEntity
    {
        if (!$data instanceof CreateInvoiceRequest) {
            throw new BadRequestHttpException('Invalid request data');
        }

        // Extract tenant context
        $tenantIdString = $context['tenant_id'] ?? null;
        if (null === $tenantIdString) {
            throw new \RuntimeException('Tenant ID not found in context');
        }

        // Validate lines
        if (empty($data->lines)) {
            throw new BadRequestHttpException('At least one line item is required');
        }

        // Generate new invoice ID
        $invoiceId = InvoiceId::generate();

        // Map request lines to command DTOs
        // Lines may arrive as InvoiceLineRequest objects or associative arrays
        // depending on the serializer configuration, so we handle both formats.
        $lineDtos = [];
        foreach ($data->lines as $line) {
            $lineData = (array) $line;
            $lineDtos[] = new GenerateInvoiceLineDto(
                description: (string) ($lineData['description'] ?? ''),
                quantity: (int) ($lineData['quantity'] ?? 0),
                unitPriceAmount: (int) ($lineData['unitPriceAmount'] ?? 0),
                unitPriceCurrency: (string) ($lineData['unitPriceCurrency'] ?? 'EUR'),
                taxRate: (float) ($lineData['taxRate'] ?? 0.0),
                productId: isset($lineData['productId']) ? (string) $lineData['productId'] : null,
                sku: isset($lineData['sku']) ? (string) $lineData['sku'] : null,
            );
        }

        // Create command
        $command = new GenerateInvoiceCommand(
            invoiceId: $invoiceId->toString(),
            tenantId: $tenantIdString,
            orderId: $data->orderId,
            customerId: $data->customerId,
            billingAddress: $data->billingAddress,
            lines: $lineDtos,
            isReverseCharge: $data->isReverseCharge,
            notes: $data->notes,
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Retrieve and return created invoice
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (null === $invoice) {
            throw new \RuntimeException('Invoice was not created successfully');
        }

        return InvoiceEntity::fromDomainModel($invoice);
    }
}
