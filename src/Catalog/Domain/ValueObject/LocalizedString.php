<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Value object for localized strings
 * Stores translations as a map of locale => string.
 */
final class LocalizedString
{
    /**
     * @param array<string, string> $translations Map of locale code to translation
     */
    private function __construct(
        private readonly array $translations,
    ) {
        $this->validate($translations);
    }

    /**
     * @param array<string, string> $translations
     */
    public static function fromArray(array $translations): self
    {
        return new self($translations);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Create with a single translation.
     */
    public static function fromSingleTranslation(string $locale, string $value): self
    {
        return new self([$locale => $value]);
    }

    /**
     * Get translation for a specific locale.
     */
    public function getTranslation(Locale $locale): ?string
    {
        return $this->translations[$locale->toString()] ?? null;
    }

    /**
     * Get translation with fallback chain.
     *
     * @param Locale[] $fallbackChain Array of locales in order of preference
     */
    public function getTranslationWithFallback(array $fallbackChain): ?string
    {
        foreach ($fallbackChain as $locale) {
            if (!$locale instanceof Locale) {
                continue;
            }

            $translation = $this->getTranslation($locale);
            if (null !== $translation) {
                return $translation;
            }
        }

        // If no translation found in fallback chain, return first available
        if (!empty($this->translations)) {
            return reset($this->translations);
        }

        return null;
    }

    /**
     * Add or update a translation.
     */
    public function withTranslation(Locale $locale, string $value): self
    {
        $translations = $this->translations;
        $translations[$locale->toString()] = $value;

        return new self($translations);
    }

    /**
     * Remove a translation.
     */
    public function withoutTranslation(Locale $locale): self
    {
        $translations = $this->translations;
        unset($translations[$locale->toString()]);

        return new self($translations);
    }

    /**
     * Merge with another LocalizedString, with other's values taking precedence.
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->translations, $other->translations));
    }

    /**
     * Get all translations.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->translations;
    }

    /**
     * Check if translation exists for a locale.
     */
    public function hasTranslation(Locale $locale): bool
    {
        return isset($this->translations[$locale->toString()]);
    }

    /**
     * Get all available locales.
     *
     * @return Locale[]
     */
    public function getAvailableLocales(): array
    {
        return array_map(
            fn (string $locale) => Locale::fromString($locale),
            array_keys($this->translations)
        );
    }

    /**
     * Check if any translations exist.
     */
    public function isEmpty(): bool
    {
        return empty($this->translations);
    }

    /**
     * Count number of translations.
     */
    public function count(): int
    {
        return count($this->translations);
    }

    public function equals(self $other): bool
    {
        return $this->translations === $other->translations;
    }

    /**
     * Get JSON representation for database storage.
     */
    public function toJson(): string
    {
        return json_encode($this->translations, JSON_THROW_ON_ERROR);
    }

    /**
     * Create from JSON string.
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return self::empty();
            }

            return new self($data);
        } catch (\JsonException) {
            return self::empty();
        }
    }

    /**
     * Validate translations array.
     *
     * @param array<string, string> $translations
     */
    private function validate(array $translations): void
    {
        foreach ($translations as $locale => $value) {
            // Validate locale format
            try {
                Locale::fromString($locale);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf('Invalid locale in translations: %s', $locale), 0, $e);
            }

            // Validate translation is a string
            if (!is_string($value)) {
                throw new \InvalidArgumentException(sprintf('Translation for locale %s must be a string', $locale));
            }
        }
    }
}
