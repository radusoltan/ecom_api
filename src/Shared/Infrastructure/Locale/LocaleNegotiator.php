<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Locale;

use App\Shared\Domain\ValueObject\LanguageCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Locale Negotiator Service.
 *
 * Provides consistent locale detection across the application using content negotiation.
 *
 * Priority order:
 * 1. Query parameter: ?locale=fr
 * 2. Accept-Language header
 * 3. Default locale (en)
 *
 * Example usage:
 * ```php
 * $locale = $localeNegotiator->negotiate($request);
 * // Returns: 'fr'
 *
 * $availableLocales = $localeNegotiator->getSupportedLocales();
 * // Returns: ['en', 'fr', 'de']
 * ```
 */
final readonly class LocaleNegotiator
{
    private const DEFAULT_LOCALE = 'en';
    private const QUERY_PARAM_NAME = 'locale';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Negotiate locale from request.
     *
     * @return string The negotiated locale code (en, fr, de)
     */
    public function negotiate(?Request $request = null): string
    {
        $request = $request ?? $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return self::DEFAULT_LOCALE;
        }

        // Priority 1: Query parameter (?locale=fr)
        $queryLocale = $request->query->get(self::QUERY_PARAM_NAME);
        if (is_string($queryLocale) && $this->isValidLocale($queryLocale)) {
            return $this->normalizeLocale($queryLocale);
        }

        // Priority 2: Accept-Language header
        $preferredLanguage = $request->getPreferredLanguage($this->getSupportedLocales());
        if (null !== $preferredLanguage && $this->isValidLocale($preferredLanguage)) {
            return $this->normalizeLocale($preferredLanguage);
        }

        // Priority 3: Default locale
        return self::DEFAULT_LOCALE;
    }

    /**
     * Get the current locale from the current request.
     */
    public function getCurrentLocale(): string
    {
        return $this->negotiate();
    }

    /**
     * Check if a locale is supported.
     */
    public function isValidLocale(string $locale): bool
    {
        // Extract base language code (e.g., 'en' from 'en_US' or 'en-US')
        $baseLocale = strtok($locale, '_-');

        try {
            LanguageCode::fromString($baseLocale);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Normalize locale to base language code (en, fr, de).
     */
    public function normalizeLocale(string $locale): string
    {
        $baseLocale = strtok($locale, '_-');

        try {
            return LanguageCode::fromString($baseLocale)->value();
        } catch (\Throwable) {
            return self::DEFAULT_LOCALE;
        }
    }

    /**
     * Get all supported locales.
     *
     * @return string[]
     */
    public function getSupportedLocales(): array
    {
        return LanguageCode::all();
    }

    /**
     * Get default locale.
     */
    public function getDefaultLocale(): string
    {
        return self::DEFAULT_LOCALE;
    }

    /**
     * Get fallback locale (alias for getDefaultLocale).
     */
    public function getFallbackLocale(): string
    {
        return self::DEFAULT_LOCALE;
    }

    /**
     * Get locale information for API responses.
     *
     * @return array{current: string, default: string, supported: string[]}
     */
    public function getLocaleMetadata(): array
    {
        return [
            'current' => $this->getCurrentLocale(),
            'default' => $this->getDefaultLocale(),
            'supported' => $this->getSupportedLocales(),
        ];
    }

    /**
     * Create locale-aware cache key.
     *
     * Useful for caching translated content per locale
     */
    public function createCacheKey(string $baseKey, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();

        return sprintf('%s_%s', $baseKey, $locale);
    }
}
