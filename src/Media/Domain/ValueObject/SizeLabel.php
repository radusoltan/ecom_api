<?php

declare(strict_types=1);

namespace App\Media\Domain\ValueObject;

enum SizeLabel: string
{
    case SMALL = 'sm';
    case MEDIUM = 'md';
    case LARGE = 'lg';
    case EXTRA_LARGE = 'xl';

    public static function fromString(string $value): self
    {
        return match ($value) {
            self::SMALL->value => self::SMALL,
            self::MEDIUM->value => self::MEDIUM,
            self::LARGE->value => self::LARGE,
            self::EXTRA_LARGE->value => self::EXTRA_LARGE,
            default => throw new \InvalidArgumentException(sprintf('Unsupported thumbnail size "%s".', $value)),
        };
    }
}
