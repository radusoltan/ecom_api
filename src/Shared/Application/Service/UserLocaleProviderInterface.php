<?php

declare(strict_types=1);

namespace App\Shared\Application\Service;

/**
 * Provides the authenticated user's preferred locale.
 *
 * Abstracts user locale lookup so Shared layer doesn't depend on User context.
 */
interface UserLocaleProviderInterface
{
    /**
     * Get the current authenticated user's preferred locale, if any.
     */
    public function getPreferredLocale(): ?string;
}
