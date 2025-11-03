<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Import Translations Command
 *
 * Triggers bulk import of translations from uploaded file.
 *
 * Supports:
 * - CSV format (locale,domain,key,value)
 * - JSON format ([{locale, domain, key, value}])
 * - Dry-run mode for preview
 */
final readonly class ImportTranslations
{
    public function __construct(
        public TenantId $tenantId,
        public UploadedFile $file,
        public string $format, // 'csv' or 'json'
        public bool $dryRun = false,
    ) {}
}
