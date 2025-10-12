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
        private IndexManager $indexManager
    ) {}

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

        return [
            'id' => $product->id()->toString(),
            'tenant_id' => $product->tenantId()->toString(),
            'sku' => $product->sku()->value(),
            'name' => $name,
            'description' => $description,
            'slug' => $product->slug()->value(),
            'price' => $product->price()->getAmount() / 100, // Convert minor units to major units
            'currency' => $product->price()->getCurrency()->getCurrencyCode(),
            'status' => $product->isActive() ? 'active' : 'inactive',
            'category_ids' => $product->categoryId() !== null ? [$product->categoryId()->toString()] : [],
            'category_names' => [], // TODO: Load category names when needed
            'image_url' => $primaryImage?->url() ?? null,
            'locale' => $locale->toString(),
            'created_at' => $product->createdAt()->format('c'),
            'updated_at' => $product->updatedAt()->format('c'),
        ];
    }
}
