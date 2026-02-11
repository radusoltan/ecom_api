<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\ValueObject\NotificationPreferences;

/**
 * Data Transfer Object for customer notification preferences.
 */
final readonly class NotificationPreferencesDTO
{
    public function __construct(
        public bool $orderUpdates,
        public bool $shippingUpdates,
        public bool $promotionalOffers,
        public bool $priceDropAlerts,
        public bool $backInStockAlerts,
        public bool $securityAlerts,
        public bool $newsletterWeekly,
        public bool $preferSms,
        public ?\DateTimeImmutable $lastUpdatedAt = null
    ) {
    }

    /**
     * Create DTO from domain value object.
     */
    public static function fromDomainModel(
        NotificationPreferences $preferences,
        ?\DateTimeImmutable $lastUpdatedAt = null
    ): self {
        return new self(
            orderUpdates: $preferences->orderUpdates(),
            shippingUpdates: $preferences->shippingUpdates(),
            promotionalOffers: $preferences->promotionalOffers(),
            priceDropAlerts: $preferences->priceDropAlerts(),
            backInStockAlerts: $preferences->backInStockAlerts(),
            securityAlerts: $preferences->securityAlerts(),
            newsletterWeekly: $preferences->newsletterWeekly(),
            preferSms: $preferences->preferSms(),
            lastUpdatedAt: $lastUpdatedAt
        );
    }
}
