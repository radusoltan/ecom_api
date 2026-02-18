<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

/**
 * Result of an import operation.
 *
 * Contains statistics and errors from the import process
 */
final readonly class ImportResult
{
    /**
     * @param array<int, string> $errors Array of error messages indexed by row number
     */
    public function __construct(
        public int $totalRows,
        public int $successCount,
        public int $errorCount,
        public array $errors,
        public int $createdCount,
        public int $updatedCount,
    ) {
    }

    public function isSuccessful(): bool
    {
        return 0 === $this->errorCount;
    }

    public function hasPartialSuccess(): bool
    {
        return $this->successCount > 0 && $this->errorCount > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'created_count' => $this->createdCount,
            'updated_count' => $this->updatedCount,
            'errors' => $this->errors,
            'is_successful' => $this->isSuccessful(),
            'has_partial_success' => $this->hasPartialSuccess(),
        ];
    }
}
