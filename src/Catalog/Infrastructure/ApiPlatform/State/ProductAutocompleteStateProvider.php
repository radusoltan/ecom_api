<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\Query\AutocompleteProducts\AutocompleteProductsQuery;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductAutocompleteResource;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<ProductAutocompleteResource>
 */
final readonly class ProductAutocompleteStateProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return [];
        }

        // Get tenant ID from header
        $tenantIdString = $request->headers->get('X-Tenant-ID');
        if ($tenantIdString === null) {
            throw new \InvalidArgumentException('X-Tenant-ID header is required');
        }

        $tenantId = TenantId::fromString($tenantIdString);

        // Query parameter is required
        $queryString = $request->query->get('q');
        if ($queryString === null || $queryString === '') {
            throw new \InvalidArgumentException('Query parameter "q" is required');
        }

        if (strlen($queryString) < 2) {
            throw new \InvalidArgumentException('Query parameter "q" must be at least 2 characters');
        }

        // Get locale from query parameter or Accept-Language header
        $localeString = $request->query->get('locale');
        if ($localeString === null) {
            $localeString = $this->getLocaleFromAcceptLanguage($request->headers->get('Accept-Language'));
        }
        $locale = Locale::fromString($localeString ?? 'en_US');

        // Build query
        $query = new AutocompleteProductsQuery(
            tenantId: $tenantId,
            locale: $locale,
            query: $queryString,
            limit: min($request->query->getInt('limit', 10), 20), // Max 20 suggestions
        );

        // Execute query
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $suggestions = $handledStamp->getResult();

        // Convert to API resources
        $resources = [];
        $index = 0;

        foreach ($suggestions as $suggestion) {
            $resource = new ProductAutocompleteResource();
            $resource->id = (string) $index++; // Unique identifier for API Platform
            $resource->text = $suggestion['text'];
            $resource->score = $suggestion['score'];
            $resource->context = $suggestion['context'] ?? null;

            $resources[] = $resource;
        }

        return $resources;
    }

    private function getLocaleFromAcceptLanguage(?string $acceptLanguage): ?string
    {
        if ($acceptLanguage === null) {
            return null;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,ro;q=0.8")
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            // Extract language code before quality value
            $langCode = explode(';', trim($language))[0];

            // Convert to our locale format (en-US -> en_US)
            $locale = str_replace('-', '_', $langCode);

            // Validate against supported locales
            try {
                Locale::fromString($locale);
                return $locale;
            } catch (\InvalidArgumentException) {
                // Try base language (en_US -> en_US is already base, but en -> en_US)
                if (strlen($locale) === 2) {
                    $baseLocale = strtolower($locale) . '_' . strtoupper($locale);
                    try {
                        Locale::fromString($baseLocale);
                        return $baseLocale;
                    } catch (\InvalidArgumentException) {
                        continue;
                    }
                }
                continue;
            }
        }

        return null;
    }
}
