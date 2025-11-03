<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Service;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Translation Coverage Service
 *
 * Domain service responsible for analyzing translation coverage:
 * - Detect missing translations per locale
 * - Calculate coverage statistics
 * - Generate heatmap data for visualization
 *
 * Business Rules:
 * - Source locale (typically 'en') is the reference for complete translations
 * - Missing translations = keys in source locale but not in target locale
 * - Coverage % = (translated keys / total keys) * 100
 * - Statistics grouped by domain and locale
 */
final readonly class TranslationCoverageService
{
    /**
     * Default source locale for missing translation detection
     */
    private const DEFAULT_SOURCE_LOCALE = 'en';

    public function __construct(
        private TranslationEntryRepositoryInterface $repository,
    ) {}

    /**
     * Find missing translations for a target locale
     *
     * Compares translations in source locale (default: 'en') with target locale
     * and returns keys that exist in source but missing in target.
     *
     * @return array<array{key: string, domain: string}>
     */
    public function findMissingTranslations(
        TenantId $tenantId,
        LanguageCode $targetLocale,
        ?LanguageCode $sourceLocale = null,
        ?TranslationDomain $domain = null,
    ): array {
        $sourceLocale ??= LanguageCode::fromString(self::DEFAULT_SOURCE_LOCALE);

        return $this->repository->findMissingInLocale(
            tenantId: $tenantId,
            sourceLocale: $sourceLocale,
            targetLocale: $targetLocale,
            domain: $domain,
        );
    }

    /**
     * Get comprehensive translation statistics
     *
     * Returns coverage data grouped by:
     * - Domain (messages, validators, emails, etc.)
     * - Locale (en, fr, de, ro)
     * - Overall totals
     *
     * @return TranslationStatistics
     */
    public function getStatistics(TenantId $tenantId): TranslationStatistics
    {
        $data = $this->repository->getStatistics($tenantId);

        return new TranslationStatistics($data);
    }

    /**
     * Get coverage heatmap data
     *
     * Returns a matrix of coverage percentages for visualization:
     * [domain][locale] = coverage%
     *
     * @return array<string, array<string, float>>
     */
    public function getCoverageHeatmap(TenantId $tenantId): array
    {
        $stats = $this->getStatistics($tenantId);

        return $stats->getHeatmapData();
    }

    /**
     * Get list of supported locales with data
     *
     * @return string[]
     */
    public function getAvailableLocales(TenantId $tenantId): array
    {
        $stats = $this->getStatistics($tenantId);

        return $stats->getLocales();
    }

    /**
     * Get list of domains with data
     *
     * @return string[]
     */
    public function getAvailableDomains(TenantId $tenantId): array
    {
        $stats = $this->getStatistics($tenantId);

        return $stats->getDomains();
    }
}

/**
 * Translation Statistics Value Object
 *
 * Encapsulates translation coverage statistics with convenient accessors
 */
final readonly class TranslationStatistics
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {}

    /**
     * Get all raw data
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get statistics grouped by domain
     *
     * @return array<string, array{totalKeys: int, locales: array<string, array{translatedKeys: int, coverage: float}>}>
     */
    public function getByDomain(): array
    {
        return $this->data['byDomain'] ?? [];
    }

    /**
     * Get statistics grouped by locale
     *
     * @return array<string, array{totalKeys: int, translatedKeys: int, coverage: float, domains: array<string, mixed>}>
     */
    public function getByLocale(): array
    {
        return $this->data['byLocale'] ?? [];
    }

    /**
     * Get overall statistics
     *
     * @return array{totalKeys: int, domains: int}
     */
    public function getOverall(): array
    {
        return $this->data['overall'] ?? ['totalKeys' => 0, 'domains' => 0];
    }

    /**
     * Get coverage heatmap data: [domain][locale] = coverage%
     *
     * @return array<string, array<string, float>>
     */
    public function getHeatmapData(): array
    {
        $heatmap = [];

        foreach ($this->getByDomain() as $domain => $domainData) {
            $heatmap[$domain] = [];

            foreach ($domainData['locales'] as $locale => $localeData) {
                $heatmap[$domain][$locale] = $localeData['coverage'];
            }
        }

        return $heatmap;
    }

    /**
     * Get list of locales present in data
     *
     * @return string[]
     */
    public function getLocales(): array
    {
        return array_keys($this->getByLocale());
    }

    /**
     * Get list of domains present in data
     *
     * @return string[]
     */
    public function getDomains(): array
    {
        return array_keys($this->getByDomain());
    }

    /**
     * Get coverage for a specific domain and locale
     */
    public function getCoverageForDomainLocale(string $domain, string $locale): ?float
    {
        return $this->data['byDomain'][$domain]['locales'][$locale]['coverage'] ?? null;
    }

    /**
     * Get total translated keys for a locale
     */
    public function getTranslatedKeysForLocale(string $locale): int
    {
        return $this->data['byLocale'][$locale]['translatedKeys'] ?? 0;
    }

    /**
     * Get overall coverage for a locale
     */
    public function getCoverageForLocale(string $locale): float
    {
        return $this->data['byLocale'][$locale]['coverage'] ?? 0.0;
    }
}
