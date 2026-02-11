<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

/**
 * Enum representing the source of a price change.
 *
 * Business Rules:
 * - MANUAL: Price changed manually by user or via price list rule
 * - IMPORT: Price changed via bulk import
 * - PROMOTION: Price changed due to promotion activation/deactivation
 * - FLASH_SALE: Price changed due to flash sale activation/end
 */
enum PriceChangeSource: string
{
    case MANUAL = 'manual';
    case IMPORT = 'import';
    case PROMOTION = 'promotion';
    case FLASH_SALE = 'flash_sale';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual Change',
            self::IMPORT => 'Bulk Import',
            self::PROMOTION => 'Promotion',
            self::FLASH_SALE => 'Flash Sale',
        };
    }

    public function isAutomated(): bool
    {
        return match ($this) {
            self::PROMOTION, self::FLASH_SALE => true,
            self::MANUAL, self::IMPORT => false,
        };
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    public static function all(): array
    {
        return self::cases();
    }

    public static function values(): array
    {
        return array_map(fn(self $source) => $source->value, self::cases());
    }
}
