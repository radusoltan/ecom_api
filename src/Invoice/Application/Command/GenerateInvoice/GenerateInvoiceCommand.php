<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\GenerateInvoice;

/**
 * Command to generate a draft invoice from order data.
 *
 * Creates a new invoice in DRAFT status that can be modified
 * before being issued.
 *
 * Business Rules:
 * - Invoice starts in DRAFT status
 * - At least one line item is required
 * - Billing address must be valid
 * - Reverse charge applies when VAT number is provided and country differs from tenant
 */
final readonly class GenerateInvoiceCommand
{
    /**
     * @param string                                                                                                                                          $invoiceId       Invoice identifier (UUID)
     * @param string                                                                                                                                          $tenantId        Tenant identifier (UUID)
     * @param string                                                                                                                                          $orderId         Associated order identifier (UUID)
     * @param string                                                                                                                                          $customerId      Customer identifier (UUID)
     * @param array{name: string, addressLine1: string, addressLine2: string|null, city: string, postalCode: string, country: string, vatNumber: string|null} $billingAddress  Billing address data
     * @param GenerateInvoiceLineDto[]                                                                                                                        $lines           Array of line items
     * @param bool                                                                                                                                            $isReverseCharge Whether reverse charge applies (B2B cross-border)
     * @param string|null                                                                                                                                     $notes           Optional notes/comments
     */
    public function __construct(
        public string $invoiceId,
        public string $tenantId,
        public string $orderId,
        public string $customerId,
        public array $billingAddress,
        public array $lines,
        public bool $isReverseCharge = false,
        public ?string $notes = null,
    ) {
    }
}
