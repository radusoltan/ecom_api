<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Locale value object for handling language codes
 * Supports formats: ll (e.g., "en") or ll_CC (e.g., "en_US").
 */
final class Locale
{
    private const PATTERN = '/^[a-z]{2}(?:_[A-Z]{2})?$/';

    // Common locale fallbacks
    private const FALLBACK_MAP = [
        'en_US' => 'en',
        'en_GB' => 'en',
        'fr_FR' => 'fr',
        'fr_CA' => 'fr',
        'de_DE' => 'de',
        'de_AT' => 'de',
        'de_CH' => 'de',
        'ro_RO' => 'ro',
        'es_ES' => 'es',
        'es_MX' => 'es',
        'pt_BR' => 'pt',
        'pt_PT' => 'pt',
    ];

    private function __construct(
        private readonly string $value,
    ) {
        $this->validate($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get the language code without country (e.g., "en" from "en_US").
     */
    public function getLanguageCode(): string
    {
        if (str_contains($this->value, '_')) {
            return substr($this->value, 0, 2);
        }

        return $this->value;
    }

    /**
     * Get the country code if present (e.g., "US" from "en_US").
     */
    public function getCountryCode(): ?string
    {
        if (str_contains($this->value, '_')) {
            return substr($this->value, 3, 2);
        }

        return null;
    }

    /**
     * Check if this locale matches or is compatible with another locale
     * en_US matches en_US (exact), en (language match).
     */
    public function isCompatibleWith(self $other): bool
    {
        // Exact match
        if ($this->equals($other)) {
            return true;
        }

        // Language-level match
        return $this->getLanguageCode() === $other->getLanguageCode();
    }

    /**
     * Get the base language locale (e.g., "en" from "en_US").
     */
    public function getBaseLocale(): self
    {
        $languageCode = $this->getLanguageCode();
        if ($languageCode === $this->value) {
            return $this;
        }

        return self::fromString($languageCode);
    }

    /**
     * Get suggested fallback locale.
     */
    public function getFallback(): ?self
    {
        // If it's already a base locale (e.g., "en"), no fallback
        if (!str_contains($this->value, '_')) {
            return null;
        }

        // Return base language as fallback
        return $this->getBaseLocale();
    }

    /**
     * Create a locale with normalized format (lowercase language, uppercase country).
     */
    public static function normalize(string $value): self
    {
        $parts = explode('_', str_replace('-', '_', $value));

        if (1 === count($parts)) {
            return self::fromString(strtolower($parts[0]));
        }

        if (2 === count($parts)) {
            return self::fromString(
                strtolower($parts[0]).'_'.strtoupper($parts[1])
            );
        }

        throw new \InvalidArgumentException(sprintf('Invalid locale format: %s', $value));
    }

    private function validate(string $value): void
    {
        if (!preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid locale format: %s. Expected format: ll or ll_CC (e.g., "en" or "en_US")', $value));
        }
    }
}
