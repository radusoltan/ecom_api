<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;

/**
 * ConfigureSubscriptionCommand.
 *
 * Command to configure a subscription for a product.
 *
 * Business Rules:
 * - Product must be of type 'subscription'
 * - Interval: daily, weekly, monthly, yearly
 * - Billing cycles: 0 = infinite, 1-999 = limited
 * - Setup fee must be >= 0
 * - Trial period end must be in future (if provided)
 */
final readonly class ConfigureSubscriptionCommand
{
    public function __construct(
        public ProductId $productId,
        public SubscriptionInterval $interval,
        public int $billingCycles,
        public Money $setupFee,
        public ?\DateTimeImmutable $trialPeriodEnd = null,
    ) {
    }
}
