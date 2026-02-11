# Elasticsearch Event Synchronization

## Overview

The Search context keeps its Elasticsearch index synchronized with the Catalog context through event-driven architecture. This document describes the implementation, architecture, and operational aspects of this synchronization.

## Architecture

### Design Principles

1. **Loose Coupling:** Search context subscribes to Catalog events without direct dependencies
2. **Graceful Degradation:** Indexing failures don't block domain operations
3. **Multi-tenancy:** Separate indices per tenant for data isolation
4. **Multi-locale:** Products indexed in all enabled locales
5. **Event-Driven:** Asynchronous processing via Symfony EventDispatcher

### Component Diagram

```
┌─────────────────────────────────────────────────────┐
│              Catalog Context                        │
│                                                     │
│  ┌─────────────┐      ┌──────────────────┐        │
│  │   Product   │─────>│  Domain Events   │        │
│  │  Aggregate  │      │  - ProductCreated│        │
│  └─────────────┘      │  - ProductUpdated│        │
│                       │  - ProductDeleted │        │
│                       └──────────┬────────┘        │
└─────────────────────────────────┼──────────────────┘
                                  │
                    Event Bus     │
                                  │
┌─────────────────────────────────┼──────────────────┐
│              Search Context      ▼                  │
│                                                     │
│  ┌──────────────────────────────────────┐          │
│  │  ProductIndexSubscriber              │          │
│  │  - onProductCreated()                │          │
│  │  - onProductUpdated()                │          │
│  │  - onProductDeactivated()            │          │
│  │  - onProductDiscontinued()           │          │
│  │  - onProductReactivated()            │          │
│  └──────────────┬───────────────────────┘          │
│                 │                                   │
│                 ▼                                   │
│  ┌──────────────────────────────────────┐          │
│  │      ProductIndexer                  │          │
│  │  - indexProduct()                    │          │
│  │  - updateProduct()                   │          │
│  │  - deleteProduct()                   │          │
│  └──────────────┬───────────────────────┘          │
│                 │                                   │
│                 ▼                                   │
│  ┌──────────────────────────────────────┐          │
│  │      IndexManager                    │          │
│  │  - createProductIndex()              │          │
│  │  - getProductIndexName()             │          │
│  └──────────────┬───────────────────────┘          │
│                 │                                   │
└─────────────────┼──────────────────────────────────┘
                  │
                  ▼
         ┌─────────────────┐
         │  Elasticsearch  │
         │   Cluster       │
         └─────────────────┘
```

## Implementation

### Event Subscribers

#### ProductIndexSubscriber

Location: `src/Search/Application/EventSubscriber/ProductIndexSubscriber.php`

**Subscribed Events:**

| Event | Method | Action |
|-------|--------|--------|
| `ProductCreated` | `onProductCreated()` | Index product in all locales |
| `ProductUpdated` | `onProductUpdated()` | Update product in all locales |
| `ProductDeactivated` | `onProductDeactivated()` | Mark as inactive (keep in index) |
| `ProductDiscontinued` | `onProductDiscontinued()` | Remove from all indices |
| `ProductReactivated` | `onProductReactivated()` | Re-index in all locales |

**Key Features:**
- Fetches product from repository (ensures latest state)
- Indexes in all enabled locales
- Comprehensive error logging with context
- Graceful error handling (doesn't throw exceptions)

#### CategoryIndexSubscriber

Location: `src/Search/Application/EventSubscriber/CategoryIndexSubscriber.php`

**Subscribed Events:**

| Event | Method | Action |
|-------|--------|--------|
| `CategoryCreated` | `onCategoryCreated()` | Log only (no products yet) |
| `CategoryUpdated` | `onCategoryUpdated()` | Reindex products in category |

**Note:** Full category update implementation requires `ProductRepository::findByCategoryId()` method.

### Service Configuration

Event subscribers are automatically registered via Symfony's autoconfiguration:

```yaml
# config/services.yaml
services:
    _defaults:
        autoconfigure: true  # Automatically registers EventSubscriberInterface
```

No manual registration required.

### Index Structure

#### Naming Convention

```
products_{tenant_id}_{locale}
```

Examples:
- `products_00000000-0000-4000-8000-000000000001_en_us`
- `products_abc12345-def6-7890-abcd-ef1234567890_fr_fr`

#### Document Structure

```json
{
  "id": "01234567-89ab-cdef-0123-456789abcdef",
  "tenant_id": "00000000-0000-4000-8000-000000000001",
  "sku": "PROD-123456",
  "is_featured": false,
  "name": "Dell XPS 15 Laptop",
  "description": "High-performance laptop with 15-inch display",
  "slug": "dell-xps-15-laptop",
  "price": 1299.99,
  "currency": "USD",
  "status": "active",
  "category_ids": ["cat-1", "cat-2"],
  "image_url": "https://cdn.example.com/products/dell-xps-15.jpg",
  "locale": "en_US",
  "created_at": "2025-01-15T10:30:00+00:00",
  "updated_at": "2025-01-15T10:30:00+00:00",
  "options": {
    "color": ["silver", "black"],
    "ram": ["16GB", "32GB"]
  },
  "average_rating": 4.5,
  "review_count": 127
}
```

## Operational Aspects

### Monitoring

#### Success Metrics

Monitor these log entries:

```
[info] Product indexed in Elasticsearch
[info] Product reindexed in Elasticsearch
[info] Discontinued product removed from Elasticsearch
```

#### Failure Detection

Monitor these error logs:

```
[error] Failed to index product
[error] Failed to reindex product
[error] Failed to remove discontinued product from index
```

**Alert Thresholds:**
- More than 5 indexing failures per minute → Page on-call engineer
- More than 10% indexing failure rate → Critical alert

#### Logging Context

All operations include structured context:

```json
{
  "product_id": "UUID",
  "tenant_id": "UUID",
  "sku": "PROD-123456",
  "locales": ["en_US", "fr_FR"],
  "error": "Connection timeout",
  "trace": "..."
}
```

### Performance

#### Current State

- Synchronous indexing in event subscribers
- ~50-100ms per product per locale
- 4 locales = ~200-400ms total per product

#### Optimization Strategies

1. **Asynchronous Processing (Recommended for production):**

```php
// Instead of direct indexing:
$this->productIndexer->indexProduct($product, $locale);

// Use message bus:
$this->messageBus->dispatch(new IndexProduct($product->id(), $locale));
```

Benefits:
- Non-blocking domain operations
- Built-in retry mechanism
- Better scalability

2. **Bulk Operations:**

```php
// Index multiple products at once:
$this->productIndexer->indexProducts($products, $locale);
```

3. **Smart Indexing:**
- Only reindex changed fields
- Skip unchanged locales
- Batch updates every 5 minutes

### Troubleshooting

#### Product Not Appearing in Search

**Diagnostic Steps:**

1. Check if product is active:
```bash
symfony console app:catalog:product-status PRODUCT_ID
```

2. Check Elasticsearch index:
```bash
curl "http://elasticsearch:9200/products_*/_search?q=id:PRODUCT_ID&pretty"
```

3. Check event subscriber logs:
```bash
grep "product_id.*PRODUCT_ID" var/log/dev.log | grep -i elasticsearch
```

4. Check Elasticsearch cluster health:
```bash
curl "http://elasticsearch:9200/_cluster/health?pretty"
```

**Solutions:**

- **Product not active:** Activate product first
- **Index missing:** Run full reindex command
- **Indexing failed:** Check logs for error details
- **Cluster unhealthy:** Restart Elasticsearch

#### Index Out of Sync

**Full Tenant Reindex:**
```bash
symfony console app:search:reindex-tenant TENANT_ID
```

**Single Product Reindex:**
```bash
symfony console app:search:reindex-product PRODUCT_ID
```

**All Products Reindex (Dangerous in production!):**
```bash
symfony console app:search:reindex-all --confirm
```

#### High Indexing Latency

**Diagnostics:**

1. Check Elasticsearch cluster metrics:
```bash
curl "http://elasticsearch:9200/_nodes/stats?pretty"
```

2. Check index size:
```bash
curl "http://elasticsearch:9200/_cat/indices/products_*?v&h=index,docs.count,store.size"
```

3. Monitor queue size (if using async):
```bash
symfony console messenger:stats
```

**Solutions:**

- Scale Elasticsearch cluster (more nodes)
- Increase JVM heap size
- Optimize index mappings
- Use index aliases for zero-downtime updates

### Maintenance

#### Daily Operations

- Monitor error logs
- Check indexing lag metrics
- Verify cluster health

#### Weekly Operations

- Review index sizes
- Analyze slow queries
- Check for failed messages in queue

#### Monthly Operations

- Full reindex of critical tenants
- Index optimization (`forcemerge`)
- Archive old indices

## Testing

### Unit Tests

```php
// tests/Unit/Search/Application/EventSubscriber/ProductIndexSubscriberTest.php

final class ProductIndexSubscriberTest extends TestCase
{
    public function testOnProductCreatedIndexesInAllLocales(): void
    {
        $product = $this->createProduct();
        $event = new ProductCreated($product->id(), $product->tenantId(), $product->sku(), 'Test Product');

        $this->productIndexer
            ->expects($this->exactly(4)) // 4 locales
            ->method('indexProduct')
            ->with($product, $this->isInstanceOf(Locale::class));

        $this->subscriber->onProductCreated($event);
    }

    public function testOnProductCreatedHandlesIndexingFailureGracefully(): void
    {
        $event = new ProductCreated(/* ... */);

        $this->productIndexer
            ->method('indexProduct')
            ->willThrowException(new \Exception('Elasticsearch unavailable'));

        // Should not throw exception
        $this->subscriber->onProductCreated($event);

        // Should log error
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to index product', $this->arrayHasKey('error'));
    }
}
```

### Integration Tests

```php
// tests/Integration/Search/ProductIndexingTest.php

final class ProductIndexingTest extends KernelTestCase
{
    use TenantTestTrait;

    public function testProductCreatedEventTriggersElasticsearchIndexing(): void
    {
        // Arrange
        $this->setTenantContext($this->getDefaultTenantId()->toString());
        $product = Product::create(/* ... */);

        // Act
        $this->productRepository->save($product);
        $this->eventDispatcher->dispatch(new ProductCreated(/* ... */));

        // Wait for async indexing (if using queue)
        sleep(1);

        // Assert
        $searchResult = $this->elasticsearchClient->search([
            'index' => 'products_*',
            'body' => [
                'query' => [
                    'term' => ['id' => $product->id()->toString()]
                ]
            ]
        ]);

        $this->assertEquals(4, $searchResult['hits']['total']['value']); // 4 locales
    }
}
```

### End-to-End Tests

```php
// tests/Functional/Search/ProductSearchTest.php

final class ProductSearchTest extends WebTestCase
{
    public function testCreatedProductAppearsInSearchResults(): void
    {
        $client = static::createClient();

        // Create product via API
        $client->request('POST', '/api/products', [
            'json' => [
                'sku' => 'TEST-123',
                'name' => 'Test Product',
                'price' => 99.99
            ],
            'headers' => [
                'X-Tenant-ID' => $this->getDefaultTenantId()->toString()
            ]
        ]);

        $this->assertResponseIsSuccessful();

        // Wait for indexing
        sleep(2);

        // Search for product
        $client->request('GET', '/api/search?q=Test Product', [
            'headers' => [
                'X-Tenant-ID' => $this->getDefaultTenantId()->toString(),
                'Accept-Language' => 'en-US'
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $searchResults = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThan(0, $searchResults['total']);
        $this->assertEquals('TEST-123', $searchResults['hits'][0]['sku']);
    }
}
```

## Future Enhancements

1. **Retry Queue**
   - Failed indexing operations should be retried automatically
   - Exponential backoff strategy
   - Dead letter queue after N failures

2. **Monitoring Dashboard**
   - Real-time indexing status
   - Lag metrics per tenant
   - Error rate charts

3. **Smart Indexing**
   - Delta updates (only changed fields)
   - Conditional indexing based on field significance
   - Batch updates with configurable intervals

4. **A/B Testing**
   - Multiple index versions for search relevance tuning
   - Traffic splitting between index versions
   - Automated relevance scoring

5. **ML Integration**
   - Product embeddings for semantic search
   - Personalized search ranking
   - Auto-complete with ML predictions

6. **Performance Optimization**
   - Index sharding strategies
   - Hot/cold architecture
   - Read replicas for search queries

7. **Analytics**
   - Search query analytics
   - Conversion tracking
   - Search performance metrics

## Related Documentation

- [Search Implementation Guide](./search-implementation.md)
- [Elasticsearch Index Configuration](./elasticsearch-indices.md)
- [Multi-tenant Search](./multi-tenant-search.md)
- [Performance Tuning](./search-performance.md)

## References

- [Elasticsearch PHP Client](https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html)
- [Symfony Event Dispatcher](https://symfony.com/doc/current/event_dispatcher.html)
- [DDD Event-Driven Architecture](../../CLAUDE.md)
- [Project Testing Guide](../technical/testing-guide.md)
