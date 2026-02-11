<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\RegeneratePdf;

/**
 * Command to regenerate PDF for an existing invoice.
 *
 * Useful for:
 * - Updating PDF template/design
 * - Fixing PDF generation errors
 * - Regenerating after system updates
 *
 * Business Rules:
 * - Only issued or paid invoices should have PDFs regenerated
 * - Draft invoices don't have PDFs yet
 * - Cancelled invoices may not need PDF regeneration
 */
final readonly class RegeneratePdfCommand
{
    /**
     * @param string $invoiceId Invoice identifier (UUID)
     */
    public function __construct(
        public string $invoiceId,
    ) {
    }
}
