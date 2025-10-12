<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use DomainException;

/**
 * Exception thrown when an invalid or unsupported language code is provided
 */
final class InvalidLanguageCodeException extends DomainException
{
    public static function unsupported(string $code): self
    {
        return new self(
            sprintf(
                'Language code "%s" is not supported. Supported languages: en, fr, de',
                $code
            )
        );
    }

    public static function empty(): self
    {
        return new self('Language code cannot be empty');
    }

    public static function invalidFormat(string $code): self
    {
        return new self(
            sprintf(
                'Language code "%s" has invalid format. Expected format: 2 lowercase letters (ISO 639-1)',
                $code
            )
        );
    }
}
