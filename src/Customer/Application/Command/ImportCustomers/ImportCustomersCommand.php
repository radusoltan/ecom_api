<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\ImportCustomers;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to import customers from CSV or JSON format.
 *
 * Supports both creating new customers and updating existing ones.
 */
final readonly class ImportCustomersCommand
{
    public function __construct(
        public TenantId $tenantId,
        public string $format,
        public string $content,
        public bool $updateExisting = false,
    ) {
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new \InvalidArgumentException('Format must be either "csv" or "json"');
        }

        if (empty($content)) {
            throw new \InvalidArgumentException('Content cannot be empty');
        }
    }

    public function isCsvFormat(): bool
    {
        return 'csv' === $this->format;
    }

    public function isJsonFormat(): bool
    {
        return 'json' === $this->format;
    }
}
