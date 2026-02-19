<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Service;

/**
 * Personal Data Inventory Service.
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
     * Export all personal data for a customer across all contexts.
     *
     * Returns machine-readable JSON format (GDPR Article 20)
     *
     * @param array{customerId: string, email?: string, tenantId?: string} $subjectContext
     *
     * @return array<string, mixed>
     */
    public function exportCustomerData(array $subjectContext): array;

    /**
     * @deprecated Anonymization is now handled via the event-driven flow:
     *             Privacy emits DataErasureRequested → Customer anonymizes data.
     *             This method is a no-op.
     *
     * @param array{customerId: string, email?: string, tenantId?: string} $subjectContext
     */
    public function anonymizeCustomerData(array $subjectContext): void;

    /**
     * List all data categories we process.
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
     * List all processing purposes.
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
     * Check if customer data can be safely deleted.
     *
     * Returns false if there are active orders, pending transactions, etc.
     *
     * @param array{customerId: string, email?: string, tenantId?: string} $subjectContext
     */
    public function canDeleteCustomerData(array $subjectContext): bool;
}
