<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Service;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Translation Export Service
 *
 * Domain service responsible for export formatting:
 * - Filter translations by criteria
 * - Format for CSV or JSON export
 * - Apply export filters (domain, locale, date range)
 *
 * Business Rules:
 * - Export format agnostic (service returns data, infrastructure formats)
 * - Filters are optional
 * - Results ordered by: domain ASC, locale ASC, key ASC
 */
final readonly class TranslationExportService
{
    public function __construct(
        private TranslationEntryRepositoryInterface $repository,
    ) {}

    /**
     * Export translations with optional filters
     *
     * @param array<string, mixed> $filters
     * @return TranslationEntry[]
     */
    public function export(
        TenantId $tenantId,
        array $filters = [],
    ): array {
        // Build filter array for repository
        $repositoryFilters = [];

        if (isset($filters['domain'])) {
            $repositoryFilters['domain'] = $filters['domain'];
        }

        if (isset($filters['locale'])) {
            $repositoryFilters['locale'] = $filters['locale'];
        }

        if (isset($filters['createdAfter'])) {
            $repositoryFilters['createdAfter'] = $filters['createdAfter'];
        }

        if (isset($filters['createdBefore'])) {
            $repositoryFilters['createdBefore'] = $filters['createdBefore'];
        }

        if (isset($filters['updatedAfter'])) {
            $repositoryFilters['updatedAfter'] = $filters['updatedAfter'];
        }

        // Fetch all matching translations (no pagination for export)
        return $this->repository->findAll(
            tenantId: $tenantId,
            filters: $repositoryFilters,
            page: 1,
            perPage: 100000, // Large number to get all results
        );
    }

    /**
     * Export translations for a specific domain
     *
     * @return TranslationEntry[]
     */
    public function exportByDomain(
        TenantId $tenantId,
        TranslationDomain $domain,
    ): array {
        return $this->export($tenantId, ['domain' => $domain->value()]);
    }

    /**
     * Export translations for a specific locale
     *
     * @return TranslationEntry[]
     */
    public function exportByLocale(
        TenantId $tenantId,
        LanguageCode $locale,
    ): array {
        return $this->export($tenantId, ['locale' => $locale->value()]);
    }

    /**
     * Export translations for a specific domain and locale
     *
     * @return TranslationEntry[]
     */
    public function exportByDomainAndLocale(
        TenantId $tenantId,
        TranslationDomain $domain,
        LanguageCode $locale,
    ): array {
        return $this->export($tenantId, [
            'domain' => $domain->value(),
            'locale' => $locale->value(),
        ]);
    }

    /**
     * Convert translation entries to array format (domain-agnostic)
     *
     * @param TranslationEntry[] $entries
     * @return array<array{locale: string, domain: string, key: string, value: string}>
     */
    public function toArrayFormat(array $entries): array
    {
        return array_map(
            fn(TranslationEntry $entry) => [
                'locale' => $entry->locale->value(),
                'domain' => $entry->domain->value(),
                'key' => $entry->key->value(),
                'value' => $entry->value->value(),
            ],
            $entries
        );
    }
}
