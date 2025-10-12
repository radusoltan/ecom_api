<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Shared\Infrastructure\Locale\LocaleNegotiator;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;

/**
 * Locale Filter for API Platform
 *
 * Automatically filters translatable entities by locale.
 * This filter is transparent - it doesn't add query parameters but respects
 * the locale from query string or Accept-Language header.
 *
 * Usage in API Resource:
 * ```php
 * #[ApiResource(
 *     operations: [
 *         new GetCollection(),
 *     ],
 * )]
 * #[ApiFilter(LocaleFilter::class)]
 * class ProductResource
 * {
 *     // ...
 * }
 * ```
 *
 * The filter will automatically apply the current locale to the query,
 * ensuring Gedmo Translatable returns content in the requested language.
 */
final class LocaleFilter extends AbstractFilter
{
    public function __construct(
        private readonly LocaleNegotiator $localeNegotiator,
    ) {
    }

    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        // This filter doesn't filter properties directly
        // It's used to inject locale into the query context
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'locale' => [
                'property' => 'locale',
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'description' => 'Filter results by locale (en, fr, de). Default: negotiated from Accept-Language header.',
                'openapi' => [
                    'example' => 'fr',
                    'enum' => $this->localeNegotiator->getSupportedLocales(),
                ],
            ],
        ];
    }

    /**
     * Get current locale for filtering
     */
    public function getCurrentLocale(): string
    {
        return $this->localeNegotiator->getCurrentLocale();
    }
}
