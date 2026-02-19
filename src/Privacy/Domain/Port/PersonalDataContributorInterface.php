<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Port;

/**
 * Personal Data Contributor Interface.
 *
 * Implemented by each bounded context that stores personal data.
 * Used by Privacy context's PersonalDataInventory to collect/audit data
 * across contexts without direct repository coupling.
 */
interface PersonalDataContributorInterface
{
    /**
     * Return the context name (e.g., 'customer', 'order', 'user').
     */
    public function contextName(): string;

    /**
     * Collect personal data for the given data subject.
     *
     * @param array{customerId: string, email?: string, tenantId?: string} $subjectContext
     *
     * @return array<string, mixed> Data from this context
     */
    public function collectPersonalData(array $subjectContext): array;

    /**
     * Check if personal data can be safely deleted from this context.
     *
     * Returns false if there are legal retention requirements or active records.
     *
     * @param array{customerId: string, email?: string, tenantId?: string} $subjectContext
     */
    public function canDeleteData(array $subjectContext): bool;
}
