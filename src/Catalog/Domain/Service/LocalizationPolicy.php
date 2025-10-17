<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;

/**
 * Domain service for handling localization fallback chains
 * Encapsulates the business logic for resolving translations
 */
final class LocalizationPolicy
{
    private const DEFAULT_LOCALE = 'en_US';

    /**
     * @param Locale[] $supportedLocales
     */
    public function __construct(
        private readonly array $supportedLocales = []
    ) {}

    /**
     * Build a fallback chain for a requested locale
     * Chain: requested locale → base language → tenant default → system default
     *
     * @return Locale[]
     */
    public function buildFallbackChain(
        Locale $requestedLocale,
        ?Locale $tenantDefaultLocale = null
    ): array {
        $chain = [];
        $seen = [];

        // 1. Add the requested locale
        $chain[] = $requestedLocale;
        $seen[$requestedLocale->toString()] = true;

        // 2. Add base language if different (e.g., "en" for "en_US")
        $baseLocale = $requestedLocale->getBaseLocale();
        if (!isset($seen[$baseLocale->toString()])) {
            $chain[] = $baseLocale;
            $seen[$baseLocale->toString()] = true;
        }

        // 3. Add tenant default if provided and different
        if ($tenantDefaultLocale !== null && !isset($seen[$tenantDefaultLocale->toString()])) {
            $chain[] = $tenantDefaultLocale;
            $seen[$tenantDefaultLocale->toString()] = true;

            // Also add base of tenant default if different
            $tenantBase = $tenantDefaultLocale->getBaseLocale();
            if (!isset($seen[$tenantBase->toString()])) {
                $chain[] = $tenantBase;
                $seen[$tenantBase->toString()] = true;
            }
        }

        // 4. Add system default as final fallback
        $systemDefault = Locale::fromString(self::DEFAULT_LOCALE);
        if (!isset($seen[$systemDefault->toString()])) {
            $chain[] = $systemDefault;
            $seen[$systemDefault->toString()] = true;
        }

        // Add base of system default if different
        $systemBase = $systemDefault->getBaseLocale();
        if (!isset($seen[$systemBase->toString()])) {
            $chain[] = $systemBase;
        }

        return $chain;
    }

    /**
     * Resolve a string from LocalizedString using fallback chain
     */
    public function resolveString(
        LocalizedString $localizedString,
        Locale $requestedLocale,
        ?Locale $tenantDefaultLocale = null
    ): ?string {
        if ($localizedString->isEmpty()) {
            return null;
        }

        $fallbackChain = $this->buildFallbackChain($requestedLocale, $tenantDefaultLocale);
        return $localizedString->getTranslationWithFallback($fallbackChain);
    }

    /**
     * Resolve multiple strings at once
     * @param array<string, LocalizedString> $localizedStrings
     * @return array<string, ?string>
     */
    public function resolveMultiple(
        array $localizedStrings,
        Locale $requestedLocale,
        ?Locale $tenantDefaultLocale = null
    ): array {
        $resolved = [];
        $fallbackChain = $this->buildFallbackChain($requestedLocale, $tenantDefaultLocale);

        foreach ($localizedStrings as $key => $localizedString) {
            if ($localizedString instanceof LocalizedString) {
                $resolved[$key] = $localizedString->getTranslationWithFallback($fallbackChain);
            } else {
                $resolved[$key] = null;
            }
        }

        return $resolved;
    }

    /**
     * Get the depth of fallback used to resolve a translation
     * Useful for metrics and debugging
     */
    public function getFallbackDepth(
        LocalizedString $localizedString,
        Locale $requestedLocale,
        ?Locale $tenantDefaultLocale = null
    ): ?int {
        if ($localizedString->isEmpty()) {
            return null;
        }

        $fallbackChain = $this->buildFallbackChain($requestedLocale, $tenantDefaultLocale);

        foreach ($fallbackChain as $depth => $locale) {
            if ($localizedString->hasTranslation($locale)) {
                return $depth;
            }
        }

        // If resolved to a translation not in the chain
        return count($fallbackChain);
    }

    /**
     * Determine which locale was actually used to resolve a translation
     */
    public function getResolvedLocale(
        LocalizedString $localizedString,
        Locale $requestedLocale,
        ?Locale $tenantDefaultLocale = null
    ): ?Locale {
        if ($localizedString->isEmpty()) {
            return null;
        }

        $fallbackChain = $this->buildFallbackChain($requestedLocale, $tenantDefaultLocale);

        foreach ($fallbackChain as $locale) {
            if ($localizedString->hasTranslation($locale)) {
                return $locale;
            }
        }

        // If none in chain, return first available
        $available = $localizedString->getAvailableLocales();
        return !empty($available) ? $available[0] : null;
    }

    /**
     * Check if a locale is supported
     */
    public function isLocaleSupported(Locale $locale): bool
    {
        if (empty($this->supportedLocales)) {
            return true; // If no restrictions, all locales are supported
        }

        foreach ($this->supportedLocales as $supported) {
            if ($locale->equals($supported) || $locale->isCompatibleWith($supported)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse Accept-Language header and return ordered list of locales
     * Example: "ro-RO, ro;q=0.8, en-US;q=0.6" → [ro_RO, ro, en_US]
     */
    public static function parseAcceptLanguageHeader(string $header): array
    {
        $locales = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $part = trim($part);

            // Extract locale and quality
            if (preg_match('/^([a-z]{2}(?:[_-][A-Z]{2})?)(?:;q=([0-9.]+))?$/i', $part, $matches)) {
                $localeString = str_replace('-', '_', $matches[1]);
                $quality = isset($matches[2]) ? (float) $matches[2] : 1.0;

                try {
                    $locale = Locale::normalize($localeString);
                    $locales[] = ['locale' => $locale, 'quality' => $quality];
                } catch (\InvalidArgumentException) {
                    // Skip invalid locales
                    continue;
                }
            }
        }

        // Sort by quality (highest first)
        usort($locales, fn($a, $b) => $b['quality'] <=> $a['quality']);

        // Return just the locale objects
        return array_map(fn($item) => $item['locale'], $locales);
    }

    /**
     * Get the best matching locale from Accept-Language header
     */
    public function negotiateLocale(
        string $acceptLanguageHeader,
        ?Locale $defaultLocale = null
    ): Locale {
        $requestedLocales = self::parseAcceptLanguageHeader($acceptLanguageHeader);

        // Try to find a supported locale
        foreach ($requestedLocales as $locale) {
            if ($this->isLocaleSupported($locale)) {
                return $locale;
            }
        }

        // Fall back to default or system default
        return $defaultLocale ?? Locale::fromString(self::DEFAULT_LOCALE);
    }
}