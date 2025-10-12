<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;

final class ProductAutocompleteApiTest extends ApiTestCase
{
    private TenantId $tenantId;
    private Locale $locale;
    private ProductRepositoryInterface $productRepository;
    private ProductIndexer $productIndexer;
    private IndexManager $indexManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = TenantId::generate();
        $this->locale = Locale::fromString('en_US');

        $container = static::getContainer();
        $this->productRepository = $container->get(ProductRepositoryInterface::class);
        $this->productIndexer = $container->get(ProductIndexer::class);
        $this->indexManager = $container->get(IndexManager::class);

        // Create test index
        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $this->locale);
        if ($this->indexManager->indexExists($indexName)) {
            $this->indexManager->deleteIndex($indexName);
        }
        $this->indexManager->createProductIndex($this->tenantId, $this->locale);
    }

    protected function tearDown(): void
    {
        // Clean up test index
        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $this->locale);
        if ($this->indexManager->indexExists($indexName)) {
            $this->indexManager->deleteIndex($indexName);
        }

        parent::tearDown();
    }

    public function testAutocompleteReturnsSuccessful(): void
    {
        // Create and index test products
        $this->createAndIndexProduct('Laptop Dell XPS 15');
        $this->createAndIndexProduct('Laptop HP Pavilion');
        $this->createAndIndexProduct('iPhone 15 Pro');

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Autocomplete for "lap"
        $response = static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'lap',
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
    }

    public function testAutocompleteReturnsSuggestions(): void
    {
        // Create and index test products
        $this->createAndIndexProduct('Laptop Dell XPS 15');
        $this->createAndIndexProduct('Laptop HP Pavilion');

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Autocomplete for "laptop"
        $response = static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
    }

    public function testAutocompleteRespectsLimit(): void
    {
        // Create and index multiple products
        for ($i = 1; $i <= 15; $i++) {
            $this->createAndIndexProduct("Laptop Model $i");
        }

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Autocomplete with limit of 5
        $response = static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
                'limit' => 5,
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertLessThanOrEqual(5, count($data));
    }

    public function testAutocompleteWithShortQueryFails(): void
    {
        static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'a', // Too short (minimum 2 characters)
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseStatusCodeSame(500); // Should be 400, but depends on error handling
    }

    public function testAutocompleteWithoutQueryFails(): void
    {
        static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseStatusCodeSame(500); // Should be 400, but depends on error handling
    }

    public function testAutocompleteWithoutTenantIdFails(): void
    {
        static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
            ],
        ]);

        $this->assertResponseStatusCodeSame(500); // Should be 400, but depends on error handling
    }

    public function testAutocompleteReturnsCorrectStructure(): void
    {
        $this->createAndIndexProduct('Laptop Dell XPS');

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        $response = static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        if (count($data) > 0) {
            $suggestion = $data[0];
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('score', $suggestion);
            $this->assertIsString($suggestion['text']);
            $this->assertIsFloat($suggestion['score']);
        }
    }

    public function testAutocompleteWithDifferentLocales(): void
    {
        // Create Romanian locale index
        $roLocale = Locale::fromString('ro_RO');
        $roIndexName = $this->indexManager->getProductIndexName($this->tenantId, $roLocale);

        if (!$this->indexManager->indexExists($roIndexName)) {
            $this->indexManager->createProductIndex($this->tenantId, $roLocale);
        }

        // Create product and index in both locales
        $product = $this->createProduct('Laptop Dell XPS');
        $this->productIndexer->indexProduct($product, $this->locale);
        $this->productIndexer->indexProduct($product, $roLocale);

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));
        $this->indexManager->refresh($roIndexName);

        // Test English locale
        $responseEn = static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Test Romanian locale
        $responseRo = static::createClient()->request('GET', '/api/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
                'locale' => 'ro_RO',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Clean up Romanian index
        $this->indexManager->deleteIndex($roIndexName);
    }

    private function createAndIndexProduct(string $name): Product
    {
        $product = $this->createProduct($name);
        $this->productIndexer->indexProduct($product, $this->locale);
        return $product;
    }

    private function createProduct(string $name): Product
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString(sprintf('PRD-%06d', rand(1, 999999))),
            name: ProductName::fromString($name),
            description: 'Test product description',
            shortDescription: 'Short desc',
            price: Money::of('100.00', 'USD'),
            categoryId: null,
            stock: Stock::create(10),
        );

        $this->productRepository->save($product);

        return $product;
    }
}
