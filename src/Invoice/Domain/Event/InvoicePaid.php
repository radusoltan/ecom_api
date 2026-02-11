<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Event;

use App\Invoice\Domain\Model\InvoiceId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: Invoice Paid.
 *
 * Triggered when an issued invoice is marked as paid.
 */
final readonly class InvoicePaid
{
    public function __construct(
        public InvoiceId $invoiceId,
        public TenantId $tenantId,
        public \DateTimeImmutable $paidDate,
        public Money $total
    ) {
    }
}
