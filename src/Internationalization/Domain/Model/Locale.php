<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Model;

use InvalidArgumentException;

final readonly class Locale implements \Stringable
{
    private const SUPPORTED_LOCALES = [
        'en_US',
        'ro_RO',
        'de_DE',
        'fr_FR',
        'es_ES',
        'it_IT',
    ];

    private function __construct(
        private string $language,
        private string $country
    ) {
        $this->validate();
    }

    public static function fromString(string $locale): self
    {
        $parts = explode('_', $locale);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException(
                sprintf('Invalid locale format "%s". Expected format: language_COUNTRY (e.g., en_US)', $locale)
            );
        }

        return new self(strtolower($parts[0]), strtoupper($parts[1]));
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function toString(): string
    {
        return $this->language . '_' . $this->country;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->language === $other->language && $this->country === $other->country;
    }

    /**
     * @return array<string>
     */
    public static function supportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    private function validate(): void
    {
        $localeString = $this->toString();

        if (!in_array($localeString, self::SUPPORTED_LOCALES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported locale "%s". Supported locales: %s',
                    $localeString,
                    implode(', ', self::SUPPORTED_LOCALES)
                )
            );
        }
    }
}
