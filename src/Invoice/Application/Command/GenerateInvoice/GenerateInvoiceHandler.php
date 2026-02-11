<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\GenerateInvoice;

use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GenerateInvoice command.
 *
 * Creates a draft invoice from order data with line items.
 * The invoice can be modified before being issued.
 */
#[AsMessageHandler]
final readonly class GenerateInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateInvoiceCommand $command): InvoiceId
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);
        $tenantId = TenantId::fromString($command->tenantId);
        $orderId = OrderId::fromString($command->orderId);
        $customerId = CustomerId::fromString($command->customerId);

        // Create billing address value object
        $billingAddress = BillingAddress::create(
            name: $command->billingAddress['name'],
            addressLine1: $command->billingAddress['addressLine1'],
            addressLine2: $command->billingAddress['addressLine2'] ?? null,
            city: $command->billingAddress['city'],
            postalCode: $command->billingAddress['postalCode'],
            country: $command->billingAddress['country'],
            vatNumber: $command->billingAddress['vatNumber'] ?? null,
        );

        // Create draft invoice
        $invoice = Invoice::createDraft(
            id: $invoiceId,
            tenantId: $tenantId,
            orderId: $orderId,
            customerId: $customerId,
            billingAddress: $billingAddress,
            isReverseCharge: $command->isReverseCharge,
            notes: $command->notes,
        );

        // Add line items
        $position = 0;
        foreach ($command->lines as $lineDto) {
            $lineId = InvoiceLineId::generate();

            $unitPrice = Money::fromScalars(
                $lineDto->unitPriceAmount,
                $lineDto->unitPriceCurrency,
            );

            $productId = null !== $lineDto->productId
                ? ProductId::fromString($lineDto->productId)
                : null;

            $line = InvoiceLine::create(
                id: $lineId,
                description: $lineDto->description,
                quantity: $lineDto->quantity,
                unitPrice: $unitPrice,
                taxRate: $lineDto->taxRate,
                productId: $productId,
                sku: $lineDto->sku,
                position: $position++,
            );

            $invoice->addLine($line);
        }

        // Save invoice (will dispatch InvoiceCreated event)
        $this->repository->save($invoice);

        $this->logger->info('Draft invoice generated', [
            'invoice_id' => $invoiceId->toString(),
            'tenant_id' => $tenantId->toString(),
            'order_id' => $orderId->toString(),
            'lines_count' => count($command->lines),
            'is_reverse_charge' => $command->isReverseCharge,
        ]);

        return $invoiceId;
    }
}
