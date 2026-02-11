<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Customer Consent Value Object.
 *
 * Immutable value object representing a customer's GDPR consents.
 * All consents default to false (opt-in model) per GDPR requirements.
 *
 * Business Rules (GDPR):
 * - All consents must default to false (explicit opt-in required)
 * - Consent can be withdrawn at any time as easily as it was given
 * - Changes to consent must be recorded with full audit trail
 * - Consent must be freely given, specific, informed, and unambiguous
 */
final readonly class CustomerConsent
{
    private function __construct(
        private bool $marketingEmail,
        private bool $marketingSms,
        private bool $thirdPartySharing,
        private bool $analyticsTracking
    ) {
    }

    /**
     * Create consent with explicit values.
     */
    public static function create(
        bool $marketingEmail,
        bool $marketingSms,
        bool $thirdPartySharing,
        bool $analyticsTracking
    ): self {
        return new self(
            $marketingEmail,
            $marketingSms,
            $thirdPartySharing,
            $analyticsTracking
        );
    }

    /**
     * Create default consent (all false - opt-in by default per GDPR).
     */
    public static function default(): self
    {
        return new self(
            marketingEmail: false,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: false
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
            marketingEmail: $data['marketingEmail'] ?? false,
            marketingSms: $data['marketingSms'] ?? false,
            thirdPartySharing: $data['thirdPartySharing'] ?? false,
            analyticsTracking: $data['analyticsTracking'] ?? false
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
            'marketingEmail' => $this->marketingEmail,
            'marketingSms' => $this->marketingSms,
            'thirdPartySharing' => $this->thirdPartySharing,
            'analyticsTracking' => $this->analyticsTracking,
        ];
    }

    /**
     * Create new instance with updated marketing email consent.
     */
    public function withMarketingEmail(bool $value): self
    {
        return new self(
            $value,
            $this->marketingSms,
            $this->thirdPartySharing,
            $this->analyticsTracking
        );
    }

    /**
     * Create new instance with updated marketing SMS consent.
     */
    public function withMarketingSms(bool $value): self
    {
        return new self(
            $this->marketingEmail,
            $value,
            $this->thirdPartySharing,
            $this->analyticsTracking
        );
    }

    /**
     * Create new instance with updated third-party sharing consent.
     */
    public function withThirdPartySharing(bool $value): self
    {
        return new self(
            $this->marketingEmail,
            $this->marketingSms,
            $value,
            $this->analyticsTracking
        );
    }

    /**
     * Create new instance with updated analytics tracking consent.
     */
    public function withAnalyticsTracking(bool $value): self
    {
        return new self(
            $this->marketingEmail,
            $this->marketingSms,
            $this->thirdPartySharing,
            $value
        );
    }

    /**
     * Check equality with another consent.
     */
    public function equals(self $other): bool
    {
        return $this->marketingEmail === $other->marketingEmail
            && $this->marketingSms === $other->marketingSms
            && $this->thirdPartySharing === $other->thirdPartySharing
            && $this->analyticsTracking === $other->analyticsTracking;
    }

    // Getters
    public function marketingEmail(): bool
    {
        return $this->marketingEmail;
    }

    public function marketingSms(): bool
    {
        return $this->marketingSms;
    }

    public function thirdPartySharing(): bool
    {
        return $this->thirdPartySharing;
    }

    public function analyticsTracking(): bool
    {
        return $this->analyticsTracking;
    }
}
