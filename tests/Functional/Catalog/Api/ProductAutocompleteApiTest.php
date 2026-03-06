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
use App\Tests\Support\TenantTestTrait;

final class ProductAutocompleteApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private TenantId $tenantId;
    private Locale $locale;
    private ProductRepositoryInterface $productRepository;
    private ProductIndexer $productIndexer;
    private IndexManager $indexManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Use default test tenant instead of random
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());
        $this->locale = Locale::fromString('en_US');

        $container = static::getContainer();
        $this->productRepository = $container->get(ProductRepositoryInterface::class);
        $this->productIndexer = $container->get(ProductIndexer::class);
        $this->indexManager = $container->get(IndexManager::class);

        // Skip if Elasticsearch is not available
        try {
            $indexName = $this->indexManager->getProductIndexName($this->tenantId, $this->locale);
            if ($this->indexManager->indexExists($indexName)) {
                $this->indexManager->deleteIndex($indexName);
            }
            $this->indexManager->createProductIndex($this->tenantId, $this->locale);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Elasticsearch is not available: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        try {
            $indexName = $this->indexManager->getProductIndexName($this->tenantId, $this->locale);
            if ($this->indexManager->indexExists($indexName)) {
                $this->indexManager->deleteIndex($indexName);
            }
        } catch (\Throwable) {
            // ES not available, nothing to clean up
        }

        $this->cleanupTestData();
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
        $response = static::createClient()->request('GET', '/api/v1/search/autocomplete', [
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
        $response = static::createClient()->request('GET', '/api/v1/search/autocomplete', [
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
        for ($i = 1; $i <= 15; ++$i) {
            $this->createAndIndexProduct("Laptop Model $i");
        }

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Autocomplete with limit of 5
        $response = static::createClient()->request('GET', '/api/v1/search/autocomplete', [
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
        static::createClient()->request('GET', '/api/v1/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'a', // Too short (minimum 2 characters)
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAutocompleteWithoutQueryFails(): void
    {
        static::createClient()->request('GET', '/api/v1/search/autocomplete', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAutocompleteWithoutTenantIdFails(): void
    {
        static::createClient()->request('GET', '/api/v1/search/autocomplete', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAutocompleteReturnsCorrectStructure(): void
    {
        $this->createAndIndexProduct('Laptop Dell XPS');

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        $response = static::createClient()->request('GET', '/api/v1/search/autocomplete', [
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
        $responseEn = static::createClient()->request('GET', '/api/v1/search/autocomplete', [
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
        $responseRo = static::createClient()->request('GET', '/api/v1/search/autocomplete', [
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
        // Generate unique SKU format: ^[A-Z]{3}-[0-9]{6}$
        $validSku = sprintf('TST-%06d', random_int(100000, 999999));

        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString($validSku),
            name: ProductName::fromString($name),
            description: 'Test product description',
            shortDescription: 'Short desc',
            price: Money::of('100.00', 'USD'),
            categoryId: null,
            stock: Stock::create(10),
        );

        // Publish product so it's searchable (new products start as DRAFT)
        $product->publish();

        $this->productRepository->save($product);

        return $product;
    }
}
