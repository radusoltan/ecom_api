<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Service;

use App\Customer\Domain\ValueObject\CustomerId;

/**
 * Personal Data Inventory Service
 *
 * GDPR Compliance - tracks all personal data across bounded contexts
 *
 * Implements:
 * - Article 15: Right to access (data export)
 * - Article 17: Right to erasure (anonymization)
 * - Article 20: Right to data portability (machine-readable export)
 * - Article 30: Records of processing activities
 */
interface PersonalDataInventoryInterface
{
    /**
     * Export all personal data for a customer across all contexts
     *
     * Returns machine-readable JSON format (GDPR Article 20)
     *
     * @return array{
     *     customer: array,
     *     user: ?array,
     *     orders: array,
     *     consents: array,
     *     loyaltyProgram: ?array,
     *     metadata: array
     * }
     */
    public function exportCustomerData(CustomerId $customerId): array;

    /**
     * Anonymize customer data across all contexts
     *
     * GDPR Article 17 - Right to erasure
     *
     * Keeps data required for legal/contractual obligations:
     * - Order history (7 years for tax compliance)
     * - Transaction records (accounting)
     * - Consent history (proof of compliance)
     *
     * Anonymizes:
     * - Customer profile (name, email, phone)
     * - User authentication data
     * - Order customer details (replace with anonymized values)
     */
    public function anonymizeCustomerData(CustomerId $customerId): void;

    /**
     * List all data categories we process
     *
     * GDPR Article 30 - Records of processing activities
     *
     * @return array<string, array{
     *     category: string,
     *     description: string,
     *     retention_period: string,
     *     lawful_basis: string,
     *     contexts: string[]
     * }>
     */
    public function getDataCategories(): array;

    /**
     * List all processing purposes
     *
     * @return array<string, array{
     *     purpose: string,
     *     description: string,
     *     lawful_basis: string,
     *     requires_consent: bool
     * }>
     */
    public function getProcessingPurposes(): array;

    /**
     * Check if customer data can be safely deleted
     *
     * Returns false if there are active orders, pending transactions, etc.
     */
    public function canDeleteCustomerData(CustomerId $customerId): bool;
}
