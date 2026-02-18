<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

/**
 * Data Transfer Object for Option.
 */
final readonly class OptionDTO
{
    /**
     * @param array<string, string> $nameTranslations
     * @param OptionValueDTO[]      $values
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $nameTranslations,
        public int $position,
        public array $values = [],
    ) {
    }
}
