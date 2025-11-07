<?php

declare(strict_types=1);

namespace App\Privacy\Domain\ValueObject;

/**
 * Consent Purpose Value Object.
 *
 * GDPR Article 6 lawful bases for processing:
 * - MARKETING: Email/SMS marketing communications (requires explicit consent)
 * - ANALYTICS: Usage analytics and tracking (requires consent)
 * - PROFILING: Behavioral profiling and personalization (requires consent)
 * - NECESSARY: Contract performance (no consent needed, always allowed)
 * - LEGAL: Legal obligation compliance (no consent needed, always allowed)
 * - THIRD_PARTY_SHARING: Data sharing with partners (requires explicit consent)
 */
final readonly class ConsentPurpose implements \Stringable
{
    private const MARKETING = 'marketing';
    private const ANALYTICS = 'analytics';
    private const PROFILING = 'profiling';
    private const NECESSARY = 'necessary';
    private const LEGAL = 'legal';
    private const THIRD_PARTY_SHARING = 'third_party_sharing';

    private const VALID_PURPOSES = [
        self::MARKETING,
        self::ANALYTICS,
        self::PROFILING,
        self::NECESSARY,
        self::LEGAL,
        self::THIRD_PARTY_SHARING,
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_PURPOSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid consent purpose: "%s". Valid purposes: %s', $value, implode(', ', self::VALID_PURPOSES)));
        }
    }

    public static function marketing(): self
    {
        return new self(self::MARKETING);
    }

    public static function analytics(): self
    {
        return new self(self::ANALYTICS);
    }

    public static function profiling(): self
    {
        return new self(self::PROFILING);
    }

    public static function necessary(): self
    {
        return new self(self::NECESSARY);
    }

    public static function legal(): self
    {
        return new self(self::LEGAL);
    }

    public static function thirdPartySharing(): self
    {
        return new self(self::THIRD_PARTY_SHARING);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function requiresExplicitConsent(): bool
    {
        return in_array($this->value, [
            self::MARKETING,
            self::ANALYTICS,
            self::PROFILING,
            self::THIRD_PARTY_SHARING,
        ], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
