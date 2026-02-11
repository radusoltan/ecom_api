<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\GenerateInvoice;

/**
 * DTO for invoice line items in GenerateInvoice command.
 *
 * Used to transfer line item data from API/UI to application layer.
 */
final readonly class GenerateInvoiceLineDto
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceAmount,
        public string $unitPriceCurrency,
        public float $taxRate,
        public ?string $productId = null,
        public ?string $sku = null,
    ) {
    }
}
