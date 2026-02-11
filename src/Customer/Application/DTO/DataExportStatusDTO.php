<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Data Export Status DTO.
 *
 * Read model for data export request status.
 */
final readonly class DataExportStatusDTO
{
    public function __construct(
        public string $id,
        public string $status,
        public ?\DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $completedAt,
        public bool $isDownloadable,
        public ?string $errorMessage = null
    ) {
    }
}
