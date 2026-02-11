<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Result of a customer export operation.
 */
final readonly class ExportResult
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $mimeType
    ) {
    }

    public static function csv(string $content): self
    {
        return new self(
            content: $content,
            filename: 'customers-export-'.date('Y-m-d-His').'.csv',
            mimeType: 'text/csv'
        );
    }

    public static function json(string $content): self
    {
        return new self(
            content: $content,
            filename: 'customers-export-'.date('Y-m-d-His').'.json',
            mimeType: 'application/json'
        );
    }
}
