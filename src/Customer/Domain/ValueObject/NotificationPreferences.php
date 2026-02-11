<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Notification Preferences Value Object.
 *
 * Immutable value object representing customer notification preferences
 * for different types of communications.
 *
 * Business Rules:
 * - orderUpdates: true (default, CANNOT be disabled - transactional requirement)
 * - shippingUpdates: true (default, can be disabled)
 * - promotionalOffers: false (default, requires marketing consent)
 * - priceDropAlerts: false (default, opt-in required)
 * - backInStockAlerts: false (default, opt-in required)
 * - securityAlerts: true (default, CANNOT be disabled - security/legal requirement)
 * - newsletterWeekly: false (default, opt-in required)
 * - preferSms: false (default, prefer email)
 * - SMS preferences require phone number to be set
 * - Promotional offers require marketing email consent
 */
final readonly class NotificationPreferences
{
    private function __construct(
        private bool $orderUpdates,
        private bool $shippingUpdates,
        private bool $promotionalOffers,
        private bool $priceDropAlerts,
        private bool $backInStockAlerts,
        private bool $securityAlerts,
        private bool $newsletterWeekly,
        private bool $preferSms
    ) {
        $this->validate();
    }

    /**
     * Create preferences with sensible defaults.
     */
    public static function default(): self
    {
        return new self(
            orderUpdates: true,           // Cannot be disabled
            shippingUpdates: true,         // Enabled by default for better UX
            promotionalOffers: false,      // Opt-in required
            priceDropAlerts: false,        // Opt-in required
            backInStockAlerts: false,      // Opt-in required
            securityAlerts: true,          // Cannot be disabled
            newsletterWeekly: false,       // Opt-in required
            preferSms: false               // Email is default channel
        );
    }

    /**
     * Create preferences with custom values.
     */
    public static function create(
        bool $orderUpdates,
        bool $shippingUpdates,
        bool $promotionalOffers,
        bool $priceDropAlerts,
        bool $backInStockAlerts,
        bool $securityAlerts,
        bool $newsletterWeekly,
        bool $preferSms
    ): self {
        return new self(
            $orderUpdates,
            $shippingUpdates,
            $promotionalOffers,
            $priceDropAlerts,
            $backInStockAlerts,
            $securityAlerts,
            $newsletterWeekly,
            $preferSms
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
            orderUpdates: $data['orderUpdates'] ?? true,
            shippingUpdates: $data['shippingUpdates'] ?? true,
            promotionalOffers: $data['promotionalOffers'] ?? false,
            priceDropAlerts: $data['priceDropAlerts'] ?? false,
            backInStockAlerts: $data['backInStockAlerts'] ?? false,
            securityAlerts: $data['securityAlerts'] ?? true,
            newsletterWeekly: $data['newsletterWeekly'] ?? false,
            preferSms: $data['preferSms'] ?? false
        );
    }

    /**
     * Convert to array for persistence.
     *
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'orderUpdates' => $this->orderUpdates,
            'shippingUpdates' => $this->shippingUpdates,
            'promotionalOffers' => $this->promotionalOffers,
            'priceDropAlerts' => $this->priceDropAlerts,
            'backInStockAlerts' => $this->backInStockAlerts,
            'securityAlerts' => $this->securityAlerts,
            'newsletterWeekly' => $this->newsletterWeekly,
            'preferSms' => $this->preferSms,
        ];
    }

    /**
     * Immutable setter for orderUpdates.
     *
     * @throws \InvalidArgumentException if attempting to disable transactional notifications
     */
    public function withOrderUpdates(bool $value): self
    {
        if (!$value) {
            throw new \InvalidArgumentException('Order updates cannot be disabled. This is a transactional notification required by law.');
        }

        return new self(
            $value,
            $this->shippingUpdates,
            $this->promotionalOffers,
            $this->priceDropAlerts,
            $this->backInStockAlerts,
            $this->securityAlerts,
            $this->newsletterWeekly,
            $this->preferSms
        );
    }

    public function withShippingUpdates(bool $value): self
    {
        return new self(
            $this->orderUpdates,
            $value,
            $this->promotionalOffers,
            $this->priceDropAlerts,
            $this->backInStockAlerts,
            $this->securityAlerts,
            $this->newsletterWeekly,
            $this->preferSms
        );
    }

    public function withPromotionalOffers(bool $value): self
    {
        return new self(
            $this->orderUpdates,
            $this->shippingUpdates,
            $value,
            $this->priceDropAlerts,
            $this->backInStockAlerts,
            $this->securityAlerts,
            $this->newsletterWeekly,
            $this->preferSms
        );
    }

    public function withPriceDropAlerts(bool $value): self
    {
        return new self(
            $this->orderUpdates,
            $this->shippingUpdates,
            $this->promotionalOffers,
            $value,
            $this->backInStockAlerts,
            $this->securityAlerts,
            $this->newsletterWeekly,
            $this->preferSms
        );
    }

    public function withBackInStockAlerts(bool $value): self
    {
        return new self(
            $this->orderUpdates,
            $this->shippingUpdates,
            $this->promotionalOffers,
            $this->priceDropAlerts,
            $value,
            $this->securityAlerts,
            $this->newsletterWeekly,
            $this->preferSms
        );
    }

    /**
     * Immutable setter for securityAlerts.
     *
     * @throws \InvalidArgumentException if attempting to disable security notifications
     */
    public function withSecurityAlerts(bool $value): self
    {
        if (!$value) {
            throw new \InvalidArgumentException('Security alerts cannot be disabled. This is required for account security and legal compliance.');
        }

        return new self(
            $this->orderUpdates,
            $this->shippingUpdates,
            $this->promotionalOffers,
            $this->priceDropAlerts,
            $this->backInStockAlerts,
            $value,
            $this->newsletterWeekly,
            $this->preferSms
        );
    }

    public function withNewsletterWeekly(bool $value): self
    {
        return new self(
            $this->orderUpdates,
            $this->shippingUpdates,
            $this->promotionalOffers,
            $this->priceDropAlerts,
            $this->backInStockAlerts,
            $this->securityAlerts,
            $value,
            $this->preferSms
        );
    }

    public function withPreferSms(bool $value): self
    {
        return new self(
            $this->orderUpdates,
            $this->shippingUpdates,
            $this->promotionalOffers,
            $this->priceDropAlerts,
            $this->backInStockAlerts,
            $this->securityAlerts,
            $this->newsletterWeekly,
            $value
        );
    }

    /**
     * Check equality with another notification preferences.
     */
    public function equals(self $other): bool
    {
        return $this->orderUpdates === $other->orderUpdates
            && $this->shippingUpdates === $other->shippingUpdates
            && $this->promotionalOffers === $other->promotionalOffers
            && $this->priceDropAlerts === $other->priceDropAlerts
            && $this->backInStockAlerts === $other->backInStockAlerts
            && $this->securityAlerts === $other->securityAlerts
            && $this->newsletterWeekly === $other->newsletterWeekly
            && $this->preferSms === $other->preferSms;
    }

    // Getters
    public function orderUpdates(): bool
    {
        return $this->orderUpdates;
    }

    public function shippingUpdates(): bool
    {
        return $this->shippingUpdates;
    }

    public function promotionalOffers(): bool
    {
        return $this->promotionalOffers;
    }

    public function priceDropAlerts(): bool
    {
        return $this->priceDropAlerts;
    }

    public function backInStockAlerts(): bool
    {
        return $this->backInStockAlerts;
    }

    public function securityAlerts(): bool
    {
        return $this->securityAlerts;
    }

    public function newsletterWeekly(): bool
    {
        return $this->newsletterWeekly;
    }

    public function preferSms(): bool
    {
        return $this->preferSms;
    }

    /**
     * Validate business rules.
     */
    private function validate(): void
    {
        // Ensure critical notifications cannot be disabled
        if (!$this->orderUpdates) {
            throw new \InvalidArgumentException('Order updates cannot be disabled. This is a transactional notification required by law.');
        }

        if (!$this->securityAlerts) {
            throw new \InvalidArgumentException('Security alerts cannot be disabled. This is required for account security and legal compliance.');
        }
    }
}
