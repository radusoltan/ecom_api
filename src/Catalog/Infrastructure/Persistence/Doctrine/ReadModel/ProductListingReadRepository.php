<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\ReadModel;

use App\Catalog\Application\DTO\MoneyDto;
use App\Catalog\Application\DTO\ProductImageDto;
use App\Catalog\Application\DTO\StorefrontProductDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class ProductListingReadRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{products: list<StorefrontProductDto>, total: int}
     */
    public function findForStorefront(
        string $tenantId,
        array $filters = [],
        int $page = 1,
        int $itemsPerPage = 24,
        string $sort = 'newest',
        string $locale = 'en',
    ): array {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);
        $locale = $this->normalizeLocale($locale);

        $where = ['p.tenant_id = :tenantId', 'p.active = :active'];
        $params = [
            'tenantId' => $tenantId,
            'active' => true,
        ];
        $types = [
            'tenantId' => ParameterType::STRING,
            'active' => ParameterType::BOOLEAN,
        ];

        if (!empty($filters['q']) && is_string($filters['q'])) {
            $where[] = '(p.name ILIKE :search OR COALESCE(p.description, \'\') ILIKE :search)';
            $params['search'] = '%'.$filters['q'].'%';
            $types['search'] = ParameterType::STRING;
        }

        if (!empty($filters['category']) && is_string($filters['category'])) {
            $where[] = 'p.category_id = :categoryId';
            $params['categoryId'] = $filters['category'];
            $types['categoryId'] = ParameterType::STRING;
        }

        if (isset($filters['priceMin']) && is_numeric($filters['priceMin'])) {
            $where[] = 'p.price_amount >= :priceMin';
            $params['priceMin'] = (int) $filters['priceMin'];
            $types['priceMin'] = ParameterType::INTEGER;
        }

        if (isset($filters['priceMax']) && is_numeric($filters['priceMax'])) {
            $where[] = 'p.price_amount <= :priceMax';
            $params['priceMax'] = (int) $filters['priceMax'];
            $types['priceMax'] = ParameterType::INTEGER;
        }

        if (($filters['isFeatured'] ?? false) === true) {
            $where[] = 'p.is_featured = :isFeatured';
            $params['isFeatured'] = true;
            $types['isFeatured'] = ParameterType::BOOLEAN;
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = match ($sort) {
            'price_asc' => 'p.price_amount ASC, p.created_at DESC',
            'price_desc' => 'p.price_amount DESC, p.created_at DESC',
            'name' => 'p.name ASC, p.created_at DESC',
            default => 'p.created_at DESC',
        };

        $countSql = "SELECT COUNT(*) FROM catalog_products p WHERE {$whereClause}";
        $total = (int) $this->connection->fetchOne($countSql, $params, $types);

        $offset = ($page - 1) * $itemsPerPage;
        $sql = <<<SQL
            SELECT
                p.id,
                p.slug,
                p.category_id,
                CASE
                    WHEN :locale <> 'en'
                        AND p.name_translations IS NOT NULL
                        AND p.name_translations->>:locale IS NOT NULL
                    THEN p.name_translations->>:locale
                    ELSE p.name
                END AS name,
                p.price_amount,
                p.price_currency,
                p.images,
                p.is_featured,
                p.stock_quantity,
                p.track_inventory,
                p.allow_backorder,
                CASE
                    WHEN :locale <> 'en'
                        AND p.description_translations IS NOT NULL
                        AND p.description_translations->>:locale IS NOT NULL
                    THEN p.description_translations->>:locale
                    ELSE p.description
                END AS description
            FROM catalog_products p
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
     * Storefront single-product lookup by slug.
     *
     * Tenant scoping is enforced by PostgreSQL RLS via the `app.tenant_id` GUC,
     * which is set upstream by TenantRequestSubscriber from the X-Tenant-ID header.
     * Inactive products and rows from other tenants are filtered transparently.
     *
     * @return StorefrontProductDto|null null when slug does not match any active product
     *                                   visible under the current tenant context
     */
    public function findOneBySlugForStorefront(string $slug, string $locale = 'en'): ?StorefrontProductDto
    {
        if ('' === $slug) {
            return null;
        }

        $locale = $this->normalizeLocale($locale);

        $sql = <<<SQL
            SELECT
                p.id,
                p.slug,
                p.category_id,
                CASE
                    WHEN :locale <> 'en'
                        AND p.name_translations IS NOT NULL
                        AND p.name_translations->>:locale IS NOT NULL
                    THEN p.name_translations->>:locale
                    ELSE p.name
                END AS name,
                p.price_amount,
                p.price_currency,
                p.images,
                p.is_featured,
                p.stock_quantity,
                p.track_inventory,
                p.allow_backorder,
                CASE
                    WHEN :locale <> 'en'
                        AND p.description_translations IS NOT NULL
                        AND p.description_translations->>:locale IS NOT NULL
                    THEN p.description_translations->>:locale
                    ELSE p.description
                END AS description
            FROM catalog_products p
            WHERE p.slug = :slug AND p.active = :active
            LIMIT 1
        SQL;

        $row = $this->connection->fetchAssociative(
            $sql,
            [
                'slug' => $slug,
                'active' => true,
                'locale' => $locale,
            ],
            [
                'slug' => ParameterType::STRING,
                'active' => ParameterType::BOOLEAN,
                'locale' => ParameterType::STRING,
            ]
        );

        if (false === $row) {
            return null;
        }

        return $this->rowToDto($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToDto(array $row): StorefrontProductDto
    {
        $images = $this->decodeJsonArray($row['images'] ?? null);
        $primaryImage = $this->extractPrimaryImage($images);
        $trackInventory = (bool) ($row['track_inventory'] ?? true);
        $allowBackorder = (bool) ($row['allow_backorder'] ?? false);
        $stockQuantity = (int) ($row['stock_quantity'] ?? 0);
        $categoryId = $row['category_id'] ?? null;

        return new StorefrontProductDto(
            id: (string) ($row['id'] ?? ''),
            slug: (string) ($row['slug'] ?? $row['id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            price: new MoneyDto(
                amount: (int) ($row['price_amount'] ?? 0),
                currency: (string) ($row['price_currency'] ?? 'USD'),
            ),
            primaryImage: $primaryImage,
            isFeatured: (bool) ($row['is_featured'] ?? false),
            rating: null,
            availability: !$trackInventory || $stockQuantity > 0 || $allowBackorder ? 'in_stock' : 'out_of_stock',
            breadcrumbs: null,
            description: isset($row['description']) ? (string) $row['description'] : null,
            categoryId: is_string($categoryId) && '' !== $categoryId ? $categoryId : null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeJsonArray(mixed $value): array
    {
        $decoded = [];

        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value) && '' !== $value) {
            $jsonDecoded = json_decode($value, true);
            $decoded = is_array($jsonDecoded) ? $jsonDecoded : [];
        }

        $images = [];
        foreach ($decoded as $image) {
            if (is_array($image)) {
                $images[] = $image;
            }
        }

        return $images;
    }

    /**
     * @param list<array<string, mixed>> $images
     */
    private function extractPrimaryImage(array $images): ?ProductImageDto
    {
        if ([] === $images) {
            return null;
        }

        $selected = null;
        foreach ($images as $image) {
            if (($image['isPrimary'] ?? false) === true) {
                $selected = $image;

                break;
            }
        }

        $selected ??= $images[0] ?? null;
        $url = is_array($selected) ? ($selected['url'] ?? null) : null;

        if (!is_string($url) || '' === $url) {
            return null;
        }

        return new ProductImageDto(
            urlSm: $url,
            urlMd: $url,
            urlLg: $url,
        );
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
