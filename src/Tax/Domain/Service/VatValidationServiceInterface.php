<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

use App\Tax\Domain\ValueObject\VatNumber;

/**
 * VAT Validation Service Port.
 *
 * This interface follows the Hexagonal Architecture pattern (Port/Adapter):
 * - Port: This interface (in Domain layer)
 * - Adapters: ViesVatValidator (real EU validation), CachedVatValidator (wrapper), MockVatValidator (testing)
 *
 * Design Principles:
 * - NO framework dependencies
 * - Immutable result DTOs
 * - Handles service unavailability gracefully
 * - Supports caching layer (decorator pattern)
 *
 * EU VIES System:
 * - VIES = VAT Information Exchange System
 * - Real-time validation via SOAP API
 * - Returns: validity + business name + address
 * - Service can be unavailable (use fallback logic)
 *
 * Use Cases:
 * 1. Checkout: Validate VAT number for B2B reverse charge
 * 2. Customer registration: Verify business customer
 * 3. Periodic revalidation: Check if VAT number still valid
 *
 * Implementations: ViesVatValidator (SOAP client), CachedVatValidator (Redis cache)
 */
interface VatValidationServiceInterface
{
    /**
     * Validate a VAT number and retrieve business details.
     *
     * This method:
     * - Connects to VIES API (or equivalent)
     * - Validates format and existence
     * - Returns business details if valid
     * - Handles timeouts and errors gracefully
     *
     * @param VatNumber $vatNumber VAT number to validate
     *
     * @return VatValidationResult Result with validity status and business details
     */
    public function validate(VatNumber $vatNumber): VatValidationResult;

    /**
     * Check if the validation service is available.
     *
     * Use this to:
     * - Verify VIES API is reachable
     * - Implement fallback logic if unavailable
     * - Skip validation during service outages
     *
     * @return bool True if service can be used for validation
     */
    public function isAvailable(): bool;
}
