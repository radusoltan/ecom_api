<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Elasticsearch;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;

final readonly class ProductIndexer
{
    public function __construct(
        private Client $client,
        private IndexManager $indexManager,
        private \Doctrine\ORM\EntityManagerInterface $entityManager,
        private \Doctrine\DBAL\Connection $connection
    ) {
    }

    public function indexProduct(Product $product, Locale $locale): void
    {
        $indexName = $this->indexManager->getProductIndexName($product->tenantId(), $locale);

        // Ensure index exists
        $this->indexManager->createProductIndex($product->tenantId(), $locale);

        $document = $this->productToDocument($product, $locale);

        $params = [
            'index' => $indexName,
            'id' => $product->id()->toString(),
            'body' => $document,
        ];

        $this->client->index($params);
    }

    /**
     * @param Product[] $products
     */
    public function indexProducts(array $products, Locale $locale): void
    {
        if (empty($products)) {
            return;
        }

        // Get tenant ID from first product (all should have same tenant)
        $tenantId = $products[0]->tenantId();
        $indexName = $this->indexManager->getProductIndexName($tenantId, $locale);

        // Ensure index exists
        $this->indexManager->createProductIndex($tenantId, $locale);

        $params = ['body' => []];

        foreach ($products as $product) {
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => $product->id()->toString(),
                ],
            ];

            $params['body'][] = $this->productToDocument($product, $locale);
        }

        if (empty($params['body'])) {
            return;
        }

        $this->client->bulk($params);
    }

    public function updateProduct(Product $product, Locale $locale): void
    {
        // Same as index - Elasticsearch will update if exists
        $this->indexProduct($product, $locale);
    }

    public function deleteProduct(ProductId $productId, TenantId $tenantId, Locale $locale): void
    {
        $indexName = $this->indexManager->getProductIndexName($tenantId, $locale);

        if (!$this->indexManager->indexExists($indexName)) {
            return;
        }

        $params = [
            'index' => $indexName,
            'id' => $productId->toString(),
        ];

        try {
            $this->client->delete($params);
        } catch (\Exception) {
            // Document might not exist - ignore
        }
    }

    public function reindexTenant(TenantId $tenantId, array $products, array $locales): void
    {
        foreach ($locales as $locale) {
            $this->indexProducts($products, $locale);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productToDocument(Product $product, Locale $locale): array
    {
        $primaryImage = !empty($product->images()) ? $product->images()[0] : null;

        // TODO: When Product supports translations, use: $product->getName($locale)
        // For now, use the single name value
        $name = $product->name()->value();
        $description = $product->description() ?? '';

        // Get all category IDs including parent hierarchy
        $categoryIds = $this->getCategoryHierarchy($product);

        // Get product options if this is a configurable product
        $options = $this->getProductOptions($product);

        // Get rating statistics
        $ratingStats = $this->getRatingStats($product->id()->toString(), $product->tenantId()->toString());

        return [
            'id' => $product->id()->toString(),
            'tenant_id' => $product->tenantId()->toString(),
            'sku' => $product->sku()->value(),
            'is_featured' => $product->isFeatured(),
            'name' => $name,
            'description' => $description,
            'slug' => $product->slug()->value(),
            'price' => $product->price()->getAmount() / 100, // Convert minor units to major units
            'currency' => $product->price()->getCurrency()->getCurrencyCode(),
            'status' => $product->isActive() ? 'active' : 'inactive',
            'category_ids' => $categoryIds,
            'category_names' => [], // TODO: Load category names when needed
            'image_url' => $primaryImage?->url() ?? null,
            'locale' => $locale->toString(),
            'created_at' => $product->createdAt()->format('c'),
            'updated_at' => $product->updatedAt()->format('c'),
            'options' => $options, // Available option values for this product
            'average_rating' => $ratingStats['average_rating'],
            'review_count' => $ratingStats['review_count'],
        ];
    }

    /**
     * Get all category IDs including parent hierarchy.
     *
     * @return array<string>
     */
    private function getCategoryHierarchy(Product $product): array
    {
        if (null === $product->categoryId()) {
            return [];
        }

        $categoryIds = [$product->categoryId()->toString()];

        // Load category entity to get parent
        $categoryEntity = $this->entityManager->find(
            \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity::class,
            $product->categoryId()->toString()
        );

        if (null === $categoryEntity) {
            return $categoryIds;
        }

        // Walk up the hierarchy to get all parent IDs
        $parentId = $categoryEntity->getParentId();
        while (null !== $parentId) {
            $categoryIds[] = $parentId;

            $parentEntity = $this->entityManager->find(
                \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity::class,
                $parentId
            );

            if (null === $parentEntity) {
                break;
            }

            $parentId = $parentEntity->getParentId();
        }

        return $categoryIds;
    }

    /**
     * Get product options if this is a configurable product
     * Returns format: ['color' => ['red', 'blue'], 'size' => ['s', 'm', 'l']].
     *
     * @return array<string, array<string>>
     */
    private function getProductOptions(Product $product): array
    {
        // Load ConfigurableProduct entity
        $configurableProductEntity = $this->entityManager->getRepository(
            \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ConfigurableProductEntity::class
        )->findOneBy(['productId' => $product->id()->toString()]);

        if (null === $configurableProductEntity) {
            return [];
        }

        // Convert to domain model
        $configurableProduct = $configurableProductEntity->toDomainModel();

        // Extract all unique option values
        $optionsMap = [];
        foreach ($configurableProduct->getOptions() as $option) {
            $optionCode = $option->getCode()->value();
            $optionsMap[$optionCode] = [];

            foreach ($option->getValues() as $optionValue) {
                $optionsMap[$optionCode][] = $optionValue->getCode()->value();
            }
        }

        return $optionsMap;
    }

    /**
     * Get rating statistics for a product.
     *
     * @return array{average_rating: float, review_count: int}
     */
    private function getRatingStats(string $productId, string $tenantId): array
    {
        $sql = "
            SELECT
                AVG(rating)::float as average_rating,
                COUNT(*)::int as review_count
            FROM product_reviews
            WHERE product_id = :product_id
            AND tenant_id = :tenant_id
            AND status = 'approved'
        ";

        $result = $this->connection->executeQuery($sql, [
            'product_id' => $productId,
            'tenant_id' => $tenantId,
        ])->fetchAssociative();

        return [
            'average_rating' => $result['average_rating'] ?? 0.0,
            'review_count' => $result['review_count'] ?? 0,
        ];
    }
}
