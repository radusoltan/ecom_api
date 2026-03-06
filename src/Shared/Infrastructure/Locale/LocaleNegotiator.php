<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Locale;

use App\Shared\Application\Service\TenantLocaleProviderInterface;
use App\Shared\Application\Service\UserLocaleProviderInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Locale Negotiator Service.
 *
 * Provides consistent locale detection using PRD §4.7 5-step priority chain:
 *
 * 1. Query parameter: ?locale=fr
 * 2. Accept-Language header
 * 3. User preferred locale (authenticated users)
 * 4. Tenant default locale
 * 5. en fallback
 */
final readonly class LocaleNegotiator
{
    private const DEFAULT_LOCALE = 'en';
    private const QUERY_PARAM_NAME = 'locale';

    public function __construct(
        private RequestStack $requestStack,
        private UserLocaleProviderInterface $userLocaleProvider,
        private TenantLocaleProviderInterface $tenantLocaleProvider,
    ) {
    }

    /**
     * Negotiate locale from request using 5-step PRD priority chain.
     *
     * @return string The negotiated locale code (en, fr, de, ro, es, it)
     */
    public function negotiate(?Request $request = null): string
    {
        $request = $request ?? $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return self::DEFAULT_LOCALE;
        }

        // Step 1: Query parameter (?locale=fr)
        $queryLocale = $request->query->get(self::QUERY_PARAM_NAME);
        if (is_string($queryLocale) && $this->isValidLocale($queryLocale)) {
            return $this->normalizeLocale($queryLocale);
        }

        // Step 2: Accept-Language header (only if actually present)
        if ($request->headers->has('Accept-Language')) {
            $preferredLanguage = $request->getPreferredLanguage($this->getSupportedLocales());
            if (null !== $preferredLanguage && $this->isValidLocale($preferredLanguage)) {
                return $this->normalizeLocale($preferredLanguage);
            }
        }

        // Step 3: User preferred locale (authenticated users only)
        $userLocale = $this->userLocaleProvider->getPreferredLocale();
        if (null !== $userLocale && $this->isValidLocale($userLocale)) {
            return $this->normalizeLocale($userLocale);
        }

        // Step 4: Tenant default locale
        $tenantLocale = $this->tenantLocaleProvider->getDefaultLocale();
        if (null !== $tenantLocale && $this->isValidLocale($tenantLocale)) {
            return $this->normalizeLocale($tenantLocale);
        }

        // Step 5: Global fallback
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
        $baseLocale = strtok($locale, '_-');

        try {
            LanguageCode::fromString($baseLocale);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Normalize locale to base language code (en, fr, de, ro, es, it).
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
     * @return string[]
     */
    public function getSupportedLocales(): array
    {
        return LanguageCode::all();
    }

    public function getDefaultLocale(): string
    {
        return self::DEFAULT_LOCALE;
    }

    public function getFallbackLocale(): string
    {
        return self::DEFAULT_LOCALE;
    }

    /**
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

    public function createCacheKey(string $baseKey, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();

        return sprintf('%s_%s', $baseKey, $locale);
    }
}
