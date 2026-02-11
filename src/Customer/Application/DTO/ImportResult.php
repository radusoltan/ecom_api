<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Result of a customer import operation.
 *
 * Contains statistics and errors from the import process.
 */
final readonly class ImportResult
{
    /**
     * @param array<int, string> $errors Array of error messages indexed by row number
     */
    public function __construct(
        public int $totalRows,
        public int $imported,
        public int $updated,
        public int $skipped,
        public array $errors
    ) {
    }

    public function isSuccessful(): bool
    {
        return empty($this->errors);
    }

    public function hasPartialSuccess(): bool
    {
        return ($this->imported > 0 || $this->updated > 0) && !empty($this->errors);
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'error_count' => $this->errorCount(),
            'errors' => $this->errors,
            'is_successful' => $this->isSuccessful(),
            'has_partial_success' => $this->hasPartialSuccess(),
        ];
    }
}
