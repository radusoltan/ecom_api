<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Event;

use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: Invoice Issued.
 *
 * Triggered when a draft invoice is issued (assigned invoice number and becomes immutable).
 */
final readonly class InvoiceIssued
{
    public function __construct(
        public InvoiceId $invoiceId,
        public TenantId $tenantId,
        public InvoiceNumber $invoiceNumber,
        public \DateTimeImmutable $issueDate,
        public Money $total,
    ) {
    }
}
