<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidLanguageCodeException;

final readonly class LanguageCode
{
    private const SUPPORTED_LANGUAGES = ['en', 'fr', 'de'];
    private const DEFAULT_LANGUAGE = 'en';

    private function __construct(
        private string $code
    ) {
        $this->validate();
    }

    public static function fromString(string $code): self
    {
        return new self(strtolower(trim($code)));
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_LANGUAGE);
    }

    public static function en(): self
    {
        return new self('en');
    }

    public static function fr(): self
    {
        return new self('fr');
    }

    public static function de(): self
    {
        return new self('de');
    }

    public static function fromAcceptLanguageHeader(?string $header): self
    {
        if (null === $header || '' === $header) {
            return self::default();
        }

        // Parse Accept-Language header (e.g., "fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7")
        $languages = explode(',', $header);

        foreach ($languages as $language) {
            // Extract language code (before '-' or ';')
            $code = strtolower(trim(explode(';', explode('-', $language)[0])[0]));

            if (in_array($code, self::SUPPORTED_LANGUAGES, true)) {
                return new self($code);
            }
        }

        return self::default();
    }

    public function toString(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function isDefault(): bool
    {
        return self::DEFAULT_LANGUAGE === $this->code;
    }

    /**
     * @return array<string>
     */
    public static function supportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /**
     * Alias for supportedLanguages() for compatibility.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    public function value(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }

    private function validate(): void
    {
        if (!in_array($this->code, self::SUPPORTED_LANGUAGES, true)) {
            throw InvalidLanguageCodeException::unsupported($this->code);
        }
    }
}
