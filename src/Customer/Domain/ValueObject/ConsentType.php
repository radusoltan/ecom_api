<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * GDPR Consent Type Enum.
 *
 * Defines the types of consent that can be granted or withdrawn by customers.
 * Each consent type represents a specific data processing activity that requires explicit opt-in per GDPR.
 */
enum ConsentType: string
{
    case MARKETING_EMAIL = 'marketing_email';
    case MARKETING_SMS = 'marketing_sms';
    case THIRD_PARTY_SHARING = 'third_party_sharing';
    case ANALYTICS_TRACKING = 'analytics_tracking';

    /**
     * Get human-readable label for the consent type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MARKETING_EMAIL => 'Marketing Emails',
            self::MARKETING_SMS => 'Marketing SMS',
            self::THIRD_PARTY_SHARING => 'Third-Party Data Sharing',
            self::ANALYTICS_TRACKING => 'Analytics Tracking',
        };
    }

    /**
     * Get detailed description for the consent type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MARKETING_EMAIL => 'Receive promotional emails about products, offers, and news',
            self::MARKETING_SMS => 'Receive promotional SMS messages about special offers and updates',
            self::THIRD_PARTY_SHARING => 'Allow sharing your data with trusted third-party partners for marketing purposes',
            self::ANALYTICS_TRACKING => 'Allow tracking your behavior for analytics and improving user experience',
        };
    }

    /**
     * Create ConsentType from string value.
     */
    public static function fromString(string $value): self
    {
        return match ($value) {
            'marketing_email' => self::MARKETING_EMAIL,
            'marketing_sms' => self::MARKETING_SMS,
            'third_party_sharing' => self::THIRD_PARTY_SHARING,
            'analytics_tracking' => self::ANALYTICS_TRACKING,
            default => throw new \InvalidArgumentException(sprintf('Invalid consent type: %s', $value)),
        };
    }
}
