<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

final readonly class TranslatedString
{
    /**
     * @param array<string, string> $translations Key is language code, value is translated text
     */
    private function __construct(
        private array $translations
    ) {
        $this->validate();
    }

    /**
     * @param array<string, string> $translations
     */
    public static function fromArray(array $translations): self
    {
        return new self($translations);
    }

    public static function fromString(string $text, ?LanguageCode $language = null): self
    {
        $lang = $language ?? LanguageCode::default();

        return new self([$lang->toString() => $text]);
    }

    public function get(LanguageCode $language): ?string
    {
        return $this->translations[$language->toString()] ?? null;
    }

    public function getOrFallback(LanguageCode $language): string
    {
        // Try requested language
        if (isset($this->translations[$language->toString()])) {
            return $this->translations[$language->toString()];
        }

        // Try default language
        $defaultLang = LanguageCode::default()->toString();
        if (isset($this->translations[$defaultLang])) {
            return $this->translations[$defaultLang];
        }

        // Return first available translation
        return reset($this->translations) ?: '';
    }

    public function withTranslation(string $text, LanguageCode $language): self
    {
        $translations = $this->translations;
        $translations[$language->toString()] = $text;

        return new self($translations);
    }

    public function hasTranslation(LanguageCode $language): bool
    {
        return isset($this->translations[$language->toString()]);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->translations;
    }

    public function isEmpty(): bool
    {
        return empty($this->translations);
    }

    /**
     * @return array<string>
     */
    public function availableLanguages(): array
    {
        return array_keys($this->translations);
    }

    private function validate(): void
    {
        if (empty($this->translations)) {
            throw new InvalidArgumentException('TranslatedString must have at least one translation');
        }

        $supportedLanguages = LanguageCode::supportedLanguages();

        foreach (array_keys($this->translations) as $langCode) {
            if (!in_array($langCode, $supportedLanguages, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Language code "%s" is not supported. Supported languages: %s',
                        $langCode,
                        implode(', ', $supportedLanguages)
                    )
                );
            }
        }

        foreach ($this->translations as $text) {
            if (!is_string($text) || trim($text) === '') {
                throw new InvalidArgumentException('Translation text cannot be empty');
            }
        }
    }
}
