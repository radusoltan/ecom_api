<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\InvoiceId;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: Invoice Created.
 *
 * Triggered when a new draft invoice is created.
 */
final readonly class InvoiceCreated
{
    public function __construct(
        public InvoiceId $invoiceId,
        public TenantId $tenantId,
        public OrderId $orderId,
        public CustomerId $customerId,
    ) {
    }
}
