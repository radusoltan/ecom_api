<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Service;

use App\Internationalization\Domain\Model\Locale;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Translation Import Service
 *
 * Domain service responsible for bulk import logic:
 * - Validation of import data
 * - Duplicate detection
 * - Dry-run mode (preview without persisting)
 * - Batch processing
 *
 * Business Rules:
 * - Duplicate keys (same locale+domain+key) → update existing
 * - Invalid locales → skip with warning
 * - Empty values → skip with warning
 * - Domain must be valid (messages, validators, emails, admin, shop)
 */
final readonly class TranslationImportService
{
    /**
     * Valid translation domains
     */
    private const VALID_DOMAINS = ['messages', 'validators', 'emails', 'admin', 'shop'];

    /**
     * Valid locales
     */
    private const VALID_LOCALES = ['en', 'fr', 'de', 'ro'];

    public function __construct(
        private TranslationEntryRepositoryInterface $repository,
    ) {}

    /**
     * Import translations from array data
     *
     * @param array<array{locale: string, domain: string, key: string, value: string}> $data
     * @param bool $dryRun If true, validate only without persisting
     * @return ImportResult
     */
    public function import(
        TenantId $tenantId,
        array $data,
        bool $dryRun = false,
    ): ImportResult {
        $result = new ImportResult();

        foreach ($data as $index => $item) {
            $lineNumber = $index + 1;

            // Validate required fields
            if (!isset($item['locale'], $item['domain'], $item['key'], $item['value'])) {
                $result->addError(
                    $lineNumber,
                    'Missing required fields (locale, domain, key, value)'
                );
                continue;
            }

            // Validate locale
            if (!in_array($item['locale'], self::VALID_LOCALES, true)) {
                $result->addWarning(
                    $lineNumber,
                    sprintf(
                        'Invalid locale "%s". Valid locales: %s',
                        $item['locale'],
                        implode(', ', self::VALID_LOCALES)
                    )
                );
                continue;
            }

            // Validate domain
            if (!in_array($item['domain'], self::VALID_DOMAINS, true)) {
                $result->addWarning(
                    $lineNumber,
                    sprintf(
                        'Invalid domain "%s". Valid domains: %s',
                        $item['domain'],
                        implode(', ', self::VALID_DOMAINS)
                    )
                );
                continue;
            }

            // Validate non-empty value
            if (trim($item['value']) === '') {
                $result->addWarning(
                    $lineNumber,
                    'Empty translation value, skipping'
                );
                continue;
            }

            try {
                $locale = LanguageCode::fromString($item['locale']);
                $domain = TranslationDomain::fromString($item['domain']);
                $key = TranslationKey::fromString($item['key']);
                $value = TranslationValue::fromString($item['value']);

                // Check if translation already exists
                $existing = $this->repository->findByKey($tenantId, $locale, $domain, $key);

                if ($existing !== null) {
                    // Update existing
                    if (!$dryRun) {
                        $updated = $existing->updateValue($value);
                        $this->repository->save($updated);
                    }
                    $result->incrementUpdated();
                } else {
                    // Create new
                    if (!$dryRun) {
                        $entry = TranslationEntry::create($tenantId, $locale, $domain, $key, $value);
                        $this->repository->save($entry);
                    }
                    $result->incrementCreated();
                }

                $result->incrementProcessed();
            } catch (\InvalidArgumentException $e) {
                $result->addError($lineNumber, $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Validate import data without persisting (dry-run)
     *
     * @param array<array{locale: string, domain: string, key: string, value: string}> $data
     */
    public function validateImport(TenantId $tenantId, array $data): ImportResult
    {
        return $this->import($tenantId, $data, dryRun: true);
    }

    /**
     * Get list of valid domains
     *
     * @return string[]
     */
    public static function getValidDomains(): array
    {
        return self::VALID_DOMAINS;
    }

    /**
     * Get list of valid locales
     *
     * @return string[]
     */
    public static function getValidLocales(): array
    {
        return self::VALID_LOCALES;
    }
}

/**
 * Import Result Value Object
 *
 * Encapsulates the result of an import operation
 */
final class ImportResult
{
    private int $processed = 0;
    private int $created = 0;
    private int $updated = 0;
    /** @var array<array{line: int, message: string}> */
    private array $errors = [];
    /** @var array<array{line: int, message: string}> */
    private array $warnings = [];

    public function incrementProcessed(): void
    {
        $this->processed++;
    }

    public function incrementCreated(): void
    {
        $this->created++;
    }

    public function incrementUpdated(): void
    {
        $this->updated++;
    }

    public function addError(int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }

    public function addWarning(int $line, string $message): void
    {
        $this->warnings[] = ['line' => $line, 'message' => $message];
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getSkipped(): int
    {
        return count($this->errors) + count($this->warnings);
    }

    /**
     * @return array<array{line: int, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<array{line: int, message: string}>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    public function isSuccessful(): bool
    {
        return !$this->hasErrors();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->getSkipped(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'successful' => $this->isSuccessful(),
        ];
    }
}
