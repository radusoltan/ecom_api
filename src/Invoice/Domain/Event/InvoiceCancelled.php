<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Event;

use App\Invoice\Domain\Model\InvoiceId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: Invoice Cancelled.
 *
 * Triggered when an issued invoice is cancelled (before payment).
 */
final readonly class InvoiceCancelled
{
    public function __construct(
        public InvoiceId $invoiceId,
        public TenantId $tenantId
    ) {
    }
}
