<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateNotificationPreferences;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to update customer notification preferences.
 */
final readonly class UpdateNotificationPreferencesCommand
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public bool $orderUpdates,
        public bool $shippingUpdates,
        public bool $promotionalOffers,
        public bool $priceDropAlerts,
        public bool $backInStockAlerts,
        public bool $securityAlerts,
        public bool $newsletterWeekly,
        public bool $preferSms,
    ) {
    }
}
