<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\ReadModel;

use App\Catalog\Application\DTO\AdminProductListDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class AdminProductReadRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array{products: list<AdminProductListDto>, total: int}
     */
    public function findForAdmin(
        string $tenantId,
        int $page = 1,
        int $itemsPerPage = 1000,
        string $sort = 'newest',
        ?string $search = null,
        ?bool $activeOnly = true,
        string $locale = 'en',
    ): array {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);
        $locale = $this->normalizeLocale($locale);

        $where = ['p.tenant_id = :tenantId'];
        $params = ['tenantId' => $tenantId];
        $types = ['tenantId' => ParameterType::STRING];

        if (null !== $activeOnly) {
            $where[] = 'p.active = :active';
            $params['active'] = $activeOnly;
            $types['active'] = ParameterType::BOOLEAN;
        }

        if (null !== $search && '' !== $search) {
            $where[] = '(p.name ILIKE :search OR p.sku ILIKE :search)';
            $params['search'] = '%'.$search.'%';
            $types['search'] = ParameterType::STRING;
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = match ($sort) {
            'price_asc' => 'p.price_amount ASC, p.created_at DESC',
            'price_desc' => 'p.price_amount DESC, p.created_at DESC',
            'name' => 'p.name ASC, p.created_at DESC',
            'sku' => 'p.sku ASC, p.created_at DESC',
            default => 'p.created_at DESC',
        };

        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM catalog_products p WHERE {$whereClause}",
            $params,
            $types,
        );

        $offset = ($page - 1) * $itemsPerPage;
        $sql = <<<SQL
            SELECT
                p.id,
                p.sku,
                CASE
                    WHEN :locale <> 'en'
                        AND p.name_translations IS NOT NULL
                        AND p.name_translations->>:locale IS NOT NULL
                    THEN p.name_translations->>:locale
                    ELSE p.name
                END AS name,
                p.slug,
                p.price_amount,
                p.price_currency,
                p.category_id,
                p.stock_quantity,
                p.track_inventory,
                p.active,
                p.is_featured,
                p.images,
                CASE
                    WHEN :locale <> 'en'
                        AND p.description_translations IS NOT NULL
                        AND p.description_translations->>:locale IS NOT NULL
                    THEN p.description_translations->>:locale
                    ELSE p.description
                END AS description,
                p.created_at,
                p.updated_at,
                COALESCE(vc.variant_count, 0) AS variant_count
            FROM catalog_products p
            LEFT JOIN LATERAL (
                SELECT COUNT(*) AS variant_count
                FROM catalog_product_variants v
                WHERE v.configurable_product_id IN (
                    SELECT cp.id
                    FROM catalog_configurable_products cp
                    WHERE cp.product_id = p.id
                )
            ) vc ON true
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        SQL;

        $params['locale'] = $locale;
        $params['limit'] = $itemsPerPage;
        $params['offset'] = $offset;
        $types['locale'] = ParameterType::STRING;
        $types['limit'] = ParameterType::INTEGER;
        $types['offset'] = ParameterType::INTEGER;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        return [
            'products' => array_map($this->rowToDto(...), $rows),
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToDto(array $row): AdminProductListDto
    {
        return new AdminProductListDto(
            id: (string) ($row['id'] ?? ''),
            sku: (string) ($row['sku'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            priceAmount: (int) ($row['price_amount'] ?? 0),
            priceCurrency: (string) ($row['price_currency'] ?? 'USD'),
            categoryId: isset($row['category_id']) ? (string) $row['category_id'] : null,
            stockQuantity: (int) ($row['stock_quantity'] ?? 0),
            trackInventory: (bool) ($row['track_inventory'] ?? true),
            active: (bool) ($row['active'] ?? false),
            isFeatured: (bool) ($row['is_featured'] ?? false),
            primaryImage: $this->extractPrimaryImage($row['images'] ?? null),
            description: isset($row['description']) ? (string) $row['description'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? ''),
            variantCount: (int) ($row['variant_count'] ?? 0),
        );
    }

    /**
     * @return array{url: string, position?: int, isPrimary?: bool}|null
     */
    private function extractPrimaryImage(mixed $value): ?array
    {
        $images = is_array($value) ? $value : json_decode((string) $value, true);

        if (!is_array($images) || [] === $images) {
            return null;
        }

        foreach ($images as $image) {
            if (is_array($image) && ($image['isPrimary'] ?? false) === true) {
                return $this->normalizePrimaryImage($image);
            }
        }

        return is_array($images[0] ?? null) ? $this->normalizePrimaryImage($images[0]) : null;
    }

    /**
     * @param array<string, mixed> $image
     *
     * @return array{url: string, position?: int, isPrimary?: bool}|null
     */
    private function normalizePrimaryImage(array $image): ?array
    {
        $url = $image['url'] ?? null;
        if (!is_string($url) || '' === $url) {
            return null;
        }

        $normalized = ['url' => $url];

        if (isset($image['position']) && is_numeric($image['position'])) {
            $normalized['position'] = (int) $image['position'];
        }

        if (isset($image['isPrimary'])) {
            $normalized['isPrimary'] = (bool) $image['isPrimary'];
        }

        return $normalized;
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = strtolower(trim($locale));
        $normalized = explode(',', $normalized)[0] ?? $normalized;
        $normalized = explode(';', $normalized)[0] ?? $normalized;
        $normalized = explode('-', $normalized)[0] ?? $normalized;

        return '' !== $normalized ? $normalized : 'en';
    }
}
