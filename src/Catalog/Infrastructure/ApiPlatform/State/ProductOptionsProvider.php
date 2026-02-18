<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\Query\GetAllProductOptions;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductOptionsResource;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductOptionValueResource;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * API Platform State Provider for Product Options.
 *
 * Fetches all unique product configuration options (color, size, etc.)
 * across the catalog using DDD/CQRS query pattern.
 *
 * Query parameters:
 * - locale: Language code (en, fr, de) - defaults to Accept-Language header or 'en'
 *
 * Requires X-Tenant-ID header for multi-tenancy.
 */
final readonly class ProductOptionsProvider implements ProviderInterface
{
    private const DEFAULT_LOCALE = 'en';

    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return ProductOptionsResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get tenant ID from header (required for multi-tenancy)
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');
        if (!$tenantIdHeader) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        try {
            $tenantId = TenantId::fromString($tenantIdHeader);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException('Invalid X-Tenant-ID header: '.$e->getMessage());
        }

        // Get locale from query parameter or Accept-Language header
        $requestedLocale = $request?->query->get('locale')
            ?? $request?->getPreferredLanguage(LanguageCode::all())
            ?? self::DEFAULT_LOCALE;

        $locale = $this->validateLocale($requestedLocale);

        // Dispatch DDD query
        $query = new GetAllProductOptions(
            tenantId: $tenantId,
            locale: $locale
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);
        /** @var \App\Catalog\Application\DTO\OptionDTO[] $optionDTOs */
        $optionDTOs = $handledStamp->getResult();

        // Convert DTOs to API Resources
        return array_map(
            fn ($optionDTO) => $this->convertToResource($optionDTO, $locale),
            $optionDTOs
        );
    }

    /**
     * Convert OptionDTO to ProductOptionsResource.
     */
    private function convertToResource(
        \App\Catalog\Application\DTO\OptionDTO $optionDTO,
        string $locale,
    ): ProductOptionsResource {
        // Get localized option name
        $optionName = $this->getLocalizedValue($optionDTO->nameTranslations, $locale);

        // Convert option values
        $valueResources = array_map(
            fn ($valueDTO) => new ProductOptionValueResource(
                code: $valueDTO->code,
                name: $this->getLocalizedValue($valueDTO->nameTranslations, $locale),
                nameTranslations: $valueDTO->nameTranslations,
            ),
            $optionDTO->values
        );

        return new ProductOptionsResource(
            code: $optionDTO->code,
            name: $optionName,
            nameTranslations: $optionDTO->nameTranslations,
            values: $valueResources,
        );
    }

    /**
     * Get localized value from translations array with fallback chain.
     *
     * @param array<string, string> $translations
     */
    private function getLocalizedValue(array $translations, string $locale): string
    {
        // Try exact locale
        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        // Try base language (e.g., 'fr' from 'fr_FR')
        $baseLocale = substr($locale, 0, 2);
        if (isset($translations[$baseLocale])) {
            return $translations[$baseLocale];
        }

        // Fallback to English
        if (isset($translations['en'])) {
            return $translations['en'];
        }

        // Last resort: first available translation
        return array_values($translations)[0] ?? '';
    }

    /**
     * Validate and normalize locale code.
     */
    private function validateLocale(string $locale): string
    {
        // Extract base language code (e.g., 'en' from 'en_US' or 'en-US')
        $baseLocale = strtok($locale, '_-');

        // Validate using LanguageCode value object (throws exception for unsupported locales)
        try {
            $languageCode = LanguageCode::fromString($baseLocale);

            return $languageCode->value();
        } catch (\InvalidArgumentException) {
            // If invalid locale, fall back to default
            return self::DEFAULT_LOCALE;
        }
    }
}
