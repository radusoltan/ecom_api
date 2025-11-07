<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Model;

/**
 * TranslationDomain Value Object.
 *
 * Represents a translation domain (messages, validators, emails, etc.)
 */
final readonly class TranslationDomain
{
    private const SUPPORTED_DOMAINS = [
        'messages',      // General UI strings
        'validators',    // Validation error messages
        'emails',        // Email templates
        'auth',          // Authentication
        'admin',         // Admin panel
        'catalog',       // Products, categories
        'inventory',     // Warehouses, stock
        'order',         // Orders, checkout
        'customer',      // Customers, addresses
        'pricing',       // Price lists, promotions
    ];

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::SUPPORTED_DOMAINS, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid translation domain "%s". Supported domains: %s', $value, implode(', ', self::SUPPORTED_DOMAINS)));
        }

        return new self($value);
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
     * @return string[]
     */
    public static function all(): array
    {
        return self::SUPPORTED_DOMAINS;
    }
}
