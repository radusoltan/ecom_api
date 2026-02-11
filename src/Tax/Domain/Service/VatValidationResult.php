<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

use App\Tax\Domain\ValueObject\VatNumber;
use DateTimeImmutable;

/**
 * VAT Validation Result DTO.
 *
 * Immutable result object from VAT number validation.
 * Contains validation status and business details from VIES.
 *
 * Design:
 * - Immutable (readonly class)
 * - Named constructors for different scenarios
 * - Includes timestamp for cache management
 * - Stores VIES request ID for audit trail
 */
final readonly class VatValidationResult
{
    /**
     * @param VatNumber $vatNumber VAT number that was validated
     * @param bool $valid True if VAT number is valid
     * @param string|null $businessName Registered business name (from VIES)
     * @param string|null $businessAddress Registered business address (from VIES)
     * @param DateTimeImmutable $validatedAt When validation was performed
     * @param string|null $requestId VIES request identifier for audit
     * @param string|null $errorMessage Error message if validation failed
     */
    private function __construct(
        public VatNumber $vatNumber,
        public bool $valid,
        public ?string $businessName,
        public ?string $businessAddress,
        public DateTimeImmutable $validatedAt,
        public ?string $requestId,
        public ?string $errorMessage
    ) {
    }

    /**
     * Create valid result with business details.
     */
    public static function valid(
        VatNumber $vatNumber,
        ?string $businessName,
        ?string $businessAddress,
        ?string $requestId = null
    ): self {
        return new self(
            vatNumber: $vatNumber,
            valid: true,
            businessName: $businessName,
            businessAddress: $businessAddress,
            validatedAt: new DateTimeImmutable(),
            requestId: $requestId,
            errorMessage: null
        );
    }

    /**
     * Create invalid result with error message.
     */
    public static function invalid(
        VatNumber $vatNumber,
        string $errorMessage,
        ?string $requestId = null
    ): self {
        return new self(
            vatNumber: $vatNumber,
            valid: false,
            businessName: null,
            businessAddress: null,
            validatedAt: new DateTimeImmutable(),
            requestId: $requestId,
            errorMessage: $errorMessage
        );
    }

    /**
     * Create service unavailable result.
     *
     * Use when VIES API is down or unreachable.
     * Allows fallback logic in application layer.
     */
    public static function serviceUnavailable(
        VatNumber $vatNumber,
        string $errorMessage
    ): self {
        return new self(
            vatNumber: $vatNumber,
            valid: false,
            businessName: null,
            businessAddress: null,
            validatedAt: new DateTimeImmutable(),
            requestId: null,
            errorMessage: $errorMessage
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function hasBusinessDetails(): bool
    {
        return $this->businessName !== null;
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    /**
     * Get age of validation result in seconds.
     */
    public function getAge(): int
    {
        $now = new DateTimeImmutable();

        return $now->getTimestamp() - $this->validatedAt->getTimestamp();
    }

    /**
     * Check if result is still fresh (for caching).
     */
    public function isFresh(int $maxAgeSeconds = 86400): bool
    {
        return $this->getAge() < $maxAgeSeconds;
    }
}
