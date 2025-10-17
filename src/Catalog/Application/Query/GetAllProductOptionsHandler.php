<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\DTO\OptionDTO;
use App\Catalog\Application\DTO\OptionValueDTO;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetAllProductOptions query
 *
 * Returns all unique options and their values across all configurable products
 * for the given tenant. Used for product filtering in shop/catalog pages.
 */
#[AsMessageHandler]
final readonly class GetAllProductOptionsHandler
{
    public function __construct(
        private Connection $connection
    ) {}

    /**
     * @return OptionDTO[]
     */
    public function __invoke(GetAllProductOptions $query): array
    {
        // Get all unique options with their values
        // Group by option code and collect all unique values
        $sql = "
            SELECT
                o.code as option_code,
                o.name_translations::text as option_name_translations,
                ov.code as value_code,
                ov.name_translations::text as value_name_translations,
                ov.position as value_position
            FROM catalog_product_options o
            INNER JOIN catalog_product_option_values ov ON ov.option_id = o.id
            INNER JOIN catalog_configurable_products cp ON cp.id = o.configurable_product_id
            WHERE cp.tenant_id = :tenant_id
            GROUP BY o.code, o.name_translations::text, ov.code, ov.name_translations::text, ov.position
            ORDER BY o.code, ov.position
        ";

        $results = $this->connection->fetchAllAssociative($sql, [
            'tenant_id' => $query->tenantId->toString(),
        ]);

        // Group results by option code
        $optionsByCode = [];
        foreach ($results as $row) {
            $optionCode = $row['option_code'];

            if (!isset($optionsByCode[$optionCode])) {
                $optionsByCode[$optionCode] = [
                    'code' => $optionCode,
                    'nameTranslations' => json_decode($row['option_name_translations'], true),
                    'values' => [],
                ];
            }

            // Add value if not already added
            $valueCode = $row['value_code'];
            $valueExists = false;
            foreach ($optionsByCode[$optionCode]['values'] as $existingValue) {
                if ($existingValue['code'] === $valueCode) {
                    $valueExists = true;
                    break;
                }
            }

            if (!$valueExists) {
                $optionsByCode[$optionCode]['values'][] = [
                    'code' => $valueCode,
                    'nameTranslations' => json_decode($row['value_name_translations'], true),
                    'position' => (int) $row['value_position'],
                ];
            }
        }

        // Convert to OptionDTO objects
        $optionDTOs = [];
        foreach ($optionsByCode as $optionData) {
            $valueDTOs = [];
            foreach ($optionData['values'] as $valueData) {
                // Get localized name if locale is specified
                $name = $this->getLocalizedValue(
                    $valueData['nameTranslations'],
                    $query->locale
                );

                $valueDTOs[] = new OptionValueDTO(
                    id: '', // Not needed for filtering
                    code: $valueData['code'],
                    nameTranslations: $valueData['nameTranslations'],
                    position: $valueData['position']
                );
            }

            // Sort values by position
            usort($valueDTOs, fn($a, $b) => $a->position <=> $b->position);

            $optionDTOs[] = new OptionDTO(
                id: '', // Not needed for filtering
                code: $optionData['code'],
                nameTranslations: $optionData['nameTranslations'],
                position: 0, // Position not relevant for global options
                values: $valueDTOs
            );
        }

        return $optionDTOs;
    }

    /**
     * Get localized value from translations array
     *
     * @param array<string, string> $translations
     */
    private function getLocalizedValue(array $translations, ?string $locale): string
    {
        if (!$locale) {
            return $translations['en'] ?? array_values($translations)[0] ?? '';
        }

        // Try exact locale
        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        // Try base language (e.g., 'fr' from 'fr_FR')
        $baseLocale = substr($locale, 0, 2);
        if (isset($translations[$baseLocale])) {
            return $translations[$baseLocale];
        }

        // Fallback to English or first available
        return $translations['en'] ?? array_values($translations)[0] ?? '';
    }
}
