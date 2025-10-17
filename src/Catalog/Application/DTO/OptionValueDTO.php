<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

/**
 * Data Transfer Object for Option Value
 */
final readonly class OptionValueDTO
{
    /**
     * @param array<string, string> $nameTranslations
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $nameTranslations,
        public int $position
    ) {}
}