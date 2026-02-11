<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Customer Preferences Value Object.
 *
 * Immutable value object representing customer preferences for language,
 * currency, and notification settings.
 *
 * Business Rules:
 * - Language must be in supported list: en, fr, de, ro
 * - Currency must be valid ISO 4217 code: EUR, USD, RON
 * - Newsletter subscription defaults to false (GDPR compliance)
 * - Marketing emails require explicit consent (default false)
 * - Order notifications enabled by default for better UX
 */
final readonly class CustomerPreferences
{
    private const SUPPORTED_LANGUAGES = ['en', 'fr', 'de', 'ro'];
    private const SUPPORTED_CURRENCIES = ['EUR', 'USD', 'RON'];
    private const DEFAULT_LANGUAGE = 'en';
    private const DEFAULT_CURRENCY = 'EUR';

    private function __construct(
        private string $preferredLanguage,
        private string $preferredCurrency,
        private bool $newsletterSubscribed,
        private bool $marketingEmailsAllowed,
        private bool $smsNotificationsAllowed,
        private bool $orderNotificationsEnabled,
        private bool $promotionNotificationsEnabled
    ) {
        $this->validate();
    }

    /**
     * Create preferences with sensible defaults (GDPR-compliant).
     */
    public static function create(
        ?string $preferredLanguage = null,
        ?string $preferredCurrency = null,
        bool $newsletterSubscribed = false,
        bool $marketingEmailsAllowed = false,
        bool $smsNotificationsAllowed = false,
        bool $orderNotificationsEnabled = true,
        bool $promotionNotificationsEnabled = false
    ): self {
        return new self(
            $preferredLanguage ?? self::DEFAULT_LANGUAGE,
            $preferredCurrency ?? self::DEFAULT_CURRENCY,
            $newsletterSubscribed,
            $marketingEmailsAllowed,
            $smsNotificationsAllowed,
            $orderNotificationsEnabled,
            $promotionNotificationsEnabled
        );
    }

    /**
     * Reconstitute from persistence (array).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['preferredLanguage'] ?? self::DEFAULT_LANGUAGE,
            $data['preferredCurrency'] ?? self::DEFAULT_CURRENCY,
            $data['newsletterSubscribed'] ?? false,
            $data['marketingEmailsAllowed'] ?? false,
            $data['smsNotificationsAllowed'] ?? false,
            $data['orderNotificationsEnabled'] ?? true,
            $data['promotionNotificationsEnabled'] ?? false
        );
    }

    /**
     * Convert to array for persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preferredLanguage' => $this->preferredLanguage,
            'preferredCurrency' => $this->preferredCurrency,
            'newsletterSubscribed' => $this->newsletterSubscribed,
            'marketingEmailsAllowed' => $this->marketingEmailsAllowed,
            'smsNotificationsAllowed' => $this->smsNotificationsAllowed,
            'orderNotificationsEnabled' => $this->orderNotificationsEnabled,
            'promotionNotificationsEnabled' => $this->promotionNotificationsEnabled,
        ];
    }

    public function withLanguage(string $language): self
    {
        if (!in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid language: "%s". Supported: %s', $language, implode(', ', self::SUPPORTED_LANGUAGES)));
        }

        return new self(
            $language,
            $this->preferredCurrency,
            $this->newsletterSubscribed,
            $this->marketingEmailsAllowed,
            $this->smsNotificationsAllowed,
            $this->orderNotificationsEnabled,
            $this->promotionNotificationsEnabled
        );
    }

    public function withCurrency(string $currency): self
    {
        $currency = strtoupper($currency);
        if (!in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid currency: "%s". Supported: %s', $currency, implode(', ', self::SUPPORTED_CURRENCIES)));
        }

        return new self(
            $this->preferredLanguage,
            $currency,
            $this->newsletterSubscribed,
            $this->marketingEmailsAllowed,
            $this->smsNotificationsAllowed,
            $this->orderNotificationsEnabled,
            $this->promotionNotificationsEnabled
        );
    }

    public function withNewsletterSubscription(bool $subscribed): self
    {
        return new self(
            $this->preferredLanguage,
            $this->preferredCurrency,
            $subscribed,
            $this->marketingEmailsAllowed,
            $this->smsNotificationsAllowed,
            $this->orderNotificationsEnabled,
            $this->promotionNotificationsEnabled
        );
    }

    public function withMarketingEmails(bool $allowed): self
    {
        return new self(
            $this->preferredLanguage,
            $this->preferredCurrency,
            $this->newsletterSubscribed,
            $allowed,
            $this->smsNotificationsAllowed,
            $this->orderNotificationsEnabled,
            $this->promotionNotificationsEnabled
        );
    }

    public function withSmsNotifications(bool $allowed): self
    {
        return new self(
            $this->preferredLanguage,
            $this->preferredCurrency,
            $this->newsletterSubscribed,
            $this->marketingEmailsAllowed,
            $allowed,
            $this->orderNotificationsEnabled,
            $this->promotionNotificationsEnabled
        );
    }

    public function withOrderNotifications(bool $enabled): self
    {
        return new self(
            $this->preferredLanguage,
            $this->preferredCurrency,
            $this->newsletterSubscribed,
            $this->marketingEmailsAllowed,
            $this->smsNotificationsAllowed,
            $enabled,
            $this->promotionNotificationsEnabled
        );
    }

    public function withPromotionNotifications(bool $enabled): self
    {
        return new self(
            $this->preferredLanguage,
            $this->preferredCurrency,
            $this->newsletterSubscribed,
            $this->marketingEmailsAllowed,
            $this->smsNotificationsAllowed,
            $this->orderNotificationsEnabled,
            $enabled
        );
    }

    public function equals(self $other): bool
    {
        return $this->preferredLanguage === $other->preferredLanguage
            && $this->preferredCurrency === $other->preferredCurrency
            && $this->newsletterSubscribed === $other->newsletterSubscribed
            && $this->marketingEmailsAllowed === $other->marketingEmailsAllowed
            && $this->smsNotificationsAllowed === $other->smsNotificationsAllowed
            && $this->orderNotificationsEnabled === $other->orderNotificationsEnabled
            && $this->promotionNotificationsEnabled === $other->promotionNotificationsEnabled;
    }

    // Getters
    public function preferredLanguage(): string
    {
        return $this->preferredLanguage;
    }

    public function preferredCurrency(): string
    {
        return $this->preferredCurrency;
    }

    public function newsletterSubscribed(): bool
    {
        return $this->newsletterSubscribed;
    }

    public function marketingEmailsAllowed(): bool
    {
        return $this->marketingEmailsAllowed;
    }

    public function smsNotificationsAllowed(): bool
    {
        return $this->smsNotificationsAllowed;
    }

    public function orderNotificationsEnabled(): bool
    {
        return $this->orderNotificationsEnabled;
    }

    public function promotionNotificationsEnabled(): bool
    {
        return $this->promotionNotificationsEnabled;
    }

    private function validate(): void
    {
        if (!in_array($this->preferredLanguage, self::SUPPORTED_LANGUAGES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid language: "%s". Supported: %s', $this->preferredLanguage, implode(', ', self::SUPPORTED_LANGUAGES)));
        }

        if (!in_array($this->preferredCurrency, self::SUPPORTED_CURRENCIES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid currency: "%s". Supported: %s', $this->preferredCurrency, implode(', ', self::SUPPORTED_CURRENCIES)));
        }
    }
}
