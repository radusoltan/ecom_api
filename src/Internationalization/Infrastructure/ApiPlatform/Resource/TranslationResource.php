<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Internationalization\Infrastructure\ApiPlatform\State\TranslationsProvider;

/**
 * API Resource for Translation Strings
 *
 * Exposes Symfony translations to frontend applications via REST API.
 * Supports multiple domains (messages, validators, emails) and locales (en, fr, de).
 *
 * Example endpoints:
 * - GET /api/translations?domain=messages&locale=en
 * - GET /api/translations?domain=validators&locale=fr
 * - GET /api/translations (all domains, default locale)
 */
#[ApiResource(
    shortName: 'Translation',
    operations: [
        new GetCollection(
            uriTemplate: '/translations',
            provider: TranslationsProvider::class,
        ),
    ],
    paginationEnabled: false,
)]
final class TranslationResource
{
    /**
     * @param string $domain Translation domain (messages, validators, emails)
     * @param string $locale Language code (en, fr, de)
     * @param array<string, mixed> $translations Flattened translation keys and values
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $locale,
        public readonly array $translations,
    ) {
    }
}
