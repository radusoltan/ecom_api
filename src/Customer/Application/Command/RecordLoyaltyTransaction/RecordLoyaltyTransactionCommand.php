<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RecordLoyaltyTransaction;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Record Loyalty Transaction Command.
 *
 * Records a loyalty point transaction and updates customer balance.
 */
final readonly class RecordLoyaltyTransactionCommand
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public TransactionType $type,
        public int $points,
        public string $reason,
        public ?string $orderId = null,
    ) {
    }
}
