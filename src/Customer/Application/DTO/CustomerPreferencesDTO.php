<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\ValueObject\CustomerPreferences;

/**
 * Customer Preferences DTO.
 *
 * Data Transfer Object for customer preferences read operations.
 */
final readonly class CustomerPreferencesDTO
{
    public function __construct(
        public string $preferredLanguage,
        public string $preferredCurrency,
        public bool $newsletterSubscribed,
        public bool $marketingEmailsAllowed,
        public bool $smsNotificationsAllowed,
        public bool $orderNotificationsEnabled,
        public bool $promotionNotificationsEnabled,
    ) {
    }

    public static function fromPreferences(CustomerPreferences $preferences): self
    {
        return new self(
            $preferences->preferredLanguage(),
            $preferences->preferredCurrency(),
            $preferences->newsletterSubscribed(),
            $preferences->marketingEmailsAllowed(),
            $preferences->smsNotificationsAllowed(),
            $preferences->orderNotificationsEnabled(),
            $preferences->promotionNotificationsEnabled()
        );
    }
}
