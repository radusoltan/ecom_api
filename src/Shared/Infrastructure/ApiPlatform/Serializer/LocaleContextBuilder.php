<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\Serializer;

use App\Shared\Infrastructure\Locale\LocaleNegotiator;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Locale Context Builder
 *
 * Adds locale metadata to API Platform serialization context.
 * This metadata can be used by normalizers to include locale information in responses.
 *
 * The locale metadata is available in the serialization context as:
 * - 'locale': Current locale (en, fr, de)
 * - 'locale_metadata': Full locale information
 */
final readonly class LocaleContextBuilder
{
    public function __construct(
        private LocaleNegotiator $localeNegotiator,
    ) {
    }

    /**
     * Add locale information to serialization context
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createFromRequest(array $context = []): array
    {
        $locale = $this->localeNegotiator->getCurrentLocale();
        $metadata = $this->localeNegotiator->getLocaleMetadata();

        return array_merge($context, [
            'locale' => $locale,
            'locale_metadata' => $metadata,
        ]);
    }
}
