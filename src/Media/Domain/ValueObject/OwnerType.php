<?php

declare(strict_types=1);

namespace App\Media\Domain\ValueObject;

enum OwnerType: string
{
    case PRODUCT = 'product';
    case CATEGORY = 'category';
    case USER = 'user';

    public static function fromString(string $value): self
    {
        return match ($value) {
            self::PRODUCT->value => self::PRODUCT,
            self::CATEGORY->value => self::CATEGORY,
            self::USER->value => self::USER,
            default => throw new \InvalidArgumentException(sprintf('Unsupported owner type "%s".', $value)),
        };
    }
}
