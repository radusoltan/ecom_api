<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

final class InvalidProductNameException extends \InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Product name cannot be empty');
    }

    public static function tooShort(string $name, int $minLength): self
    {
        return new self(
            sprintf(
                'Product name "%s" is too short. Minimum length is %d characters',
                $name,
                $minLength
            )
        );
    }

    public static function tooLong(string $name, int $maxLength): self
    {
        return new self(
            sprintf(
                'Product name is too long (%d characters). Maximum length is %d characters',
                mb_strlen($name),
                $maxLength
            )
        );
    }
}
