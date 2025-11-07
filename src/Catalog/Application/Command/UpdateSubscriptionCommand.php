<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;

/**
 * UpdateSubscriptionCommand.
 *
 * Command to update an existing subscription configuration.
 */
final readonly class UpdateSubscriptionCommand
{
    public function __construct(
        public ProductId $productId,
        public SubscriptionInterval $interval,
        public int $billingCycles,
        public Money $setupFee,
        public ?\DateTimeImmutable $trialPeriodEnd = null
    ) {
    }
}
