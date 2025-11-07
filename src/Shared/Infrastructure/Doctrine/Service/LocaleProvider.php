<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Service;

use App\Shared\Infrastructure\Locale\LocaleNegotiator;

/**
 * Locale Provider Service.
 *
 * Provides the current locale for the application based on:
 * 1. Query parameter (?locale=fr)
 * 2. Accept-Language header
 * 3. Default locale (en)
 *
 * Used by TranslatableListener to set the locale for Gedmo Translatable behavior.
 *
 * @deprecated Use LocaleNegotiator directly for new code
 */
final readonly class LocaleProvider
{
    public function __construct(
        private LocaleNegotiator $localeNegotiator,
    ) {
    }

    /**
     * Get the current locale from request or default.
     */
    public function getCurrentLocale(): string
    {
        return $this->localeNegotiator->getCurrentLocale();
    }

    /**
     * Get all supported locales.
     *
     * @return string[]
     */
    public function getSupportedLocales(): array
    {
        return $this->localeNegotiator->getSupportedLocales();
    }
}
