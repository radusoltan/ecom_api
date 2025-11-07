<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Repository;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Translation Entry Repository Interface (Port).
 *
 * Contract for translation persistence operations.
 */
interface TranslationEntryRepositoryInterface
{
    /**
     * Save a translation entry (create or update).
     */
    public function save(TranslationEntry $entry): void;

    /**
     * Delete a translation entry by ID.
     */
    public function delete(int $id, TenantId $tenantId): void;

    /**
     * Find a translation by ID.
     */
    public function findById(int $id, TenantId $tenantId): ?TranslationEntry;

    /**
     * Find a specific translation by locale, domain, and key.
     */
    public function findByKey(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
        TranslationKey $key,
    ): ?TranslationEntry;

    /**
     * Find all translations with optional filters.
     *
     * @param array<string, mixed> $filters
     *
     * @return TranslationEntry[]
     */
    public function findAll(
        TenantId $tenantId,
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
    ): array;

    /**
     * Count total translations with filters.
     *
     * @param array<string, mixed> $filters
     */
    public function count(TenantId $tenantId, array $filters = []): int;

    /**
     * Find translations that exist in one locale but missing in another.
     *
     * @return array<array{key: string, domain: string}> Array of missing translation keys with their domains
     */
    public function findMissingInLocale(
        TenantId $tenantId,
        LanguageCode $sourceLocale,
        LanguageCode $targetLocale,
        ?TranslationDomain $domain = null,
    ): array;

    /**
     * Get translation statistics per locale per domain.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(TenantId $tenantId): array;
}
