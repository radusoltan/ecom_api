# Search Event Subscribers

Event subscribers that keep the Elasticsearch index synchronized with Catalog domain events.

## Architecture

Following DDD/CQRS principles:
- **Search context** subscribes to **Catalog context** domain events
- Loose coupling via event-driven architecture
- Graceful error handling (indexing failures don't block domain operations)
- Multi-tenant support (separate indices per tenant)
- Multi-locale support (index products in all enabled locales)

## Event Subscribers

### ProductIndexSubscriber

Keeps Elasticsearch product index synchronized with product lifecycle events.

**Events handled:**

| Event | Action | Description |
|-------|--------|-------------|
| `ProductCreated` | Index | Add product to all locale indices |
| `ProductUpdated` | Update | Update product in all locale indices |
| `ProductDeactivated` | Update | Mark product as inactive (keep in index for analytics) |
| `ProductDiscontinued` | Delete | Remove product from all indices |
| `ProductReactivated` | Index | Re-add product to all indices |

**Error Handling:**
- All indexing operations are wrapped in try-catch blocks
- Failures are logged with full context (product_id, tenant_id, error, trace)
- Indexing failures don't throw exceptions (graceful degradation)
- TODO: Add retry queue for failed indexing operations
- TODO: Send notifications to ops team on failures

**Multi-locale Support:**
- Products are indexed in all enabled locales for the tenant
- Default locales: `en_US`, `fr_FR`, `de_DE`, `ro_RO`
- TODO: Fetch enabled locales from TenantSettings aggregate

**Usage:**
```php
// Automatically subscribed via Symfony's autoconfigure: true
// No manual registration needed

// When a product is created:
$product = Product::create(/* ... */);
$this->repository->save($product);
// -> ProductCreated event dispatched
// -> ProductIndexSubscriber::onProductCreated() called
// -> Product indexed in Elasticsearch
```

### CategoryIndexSubscriber

Updates product index when categories are modified.

**Events handled:**

| Event | Action | Description |
|-------|--------|-------------|
| `CategoryCreated` | None | No products exist yet |
| `CategoryUpdated` | Reindex | Reindex all products in category |

**Note:** Category updates require reindexing affected products because:
- Category names appear in search results
- Category hierarchy changes affect filtering
- TODO: Implement `ProductRepository::findByCategoryId()` for bulk reindexing

## Configuration

### Service Registration

Event subscribers are automatically registered via Symfony's service autoconfiguration:

```yaml
# config/services.yaml
services:
    _defaults:
        autoconfigure: true  # Automatically registers EventSubscriberInterface
```

### Elasticsearch Configuration

```yaml
# config/packages/elasticsearch.yaml
elasticsearch:
    hosts: ['%env(ELASTICSEARCH_HOST)%']
```

### Index Naming Convention

```
products_{tenant_id}_{locale}
```

Examples:
- `products_00000000-0000-4000-8000-000000000001_en_us`
- `products_00000000-0000-4000-8000-000000000001_fr_fr`
- `products_00000000-0000-4000-8000-000000000002_de_de`

## Testing

### Unit Tests

Test event subscribers with mocked dependencies:

```php
final class ProductIndexSubscriberTest extends TestCase
{
    public function testOnProductCreatedIndexesInAllLocales(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productIndexer = $this->createMock(ProductIndexer::class);
        $logger = $this->createMock(LoggerInterface::class);

        $subscriber = new ProductIndexSubscriber(
            $productRepository,
            $productIndexer,
            $logger
        );

        // Test implementation...
    }
}
```

### Integration Tests

Test actual Elasticsearch indexing:

```php
final class ProductIndexingIntegrationTest extends KernelTestCase
{
    use TenantTestTrait;

    public function testProductCreatedEventTriggersIndexing(): void
    {
        // Create product via repository (triggers event)
        // Verify product exists in Elasticsearch
        // Check all locales are indexed
    }
}
```

## Monitoring

### Logging

All operations are logged with structured context:

```json
{
  "message": "Product indexed in Elasticsearch",
  "context": {
    "product_id": "01234567-89ab-cdef-0123-456789abcdef",
    "tenant_id": "00000000-0000-4000-8000-000000000001",
    "sku": "PROD-123456",
    "locales": ["en_US", "fr_FR", "de_DE", "ro_RO"]
  }
}
```

### Error Tracking

Failed indexing operations are logged as errors:

```json
{
  "message": "Failed to index product",
  "level": "error",
  "context": {
    "product_id": "01234567-89ab-cdef-0123-456789abcdef",
    "tenant_id": "00000000-0000-4000-8000-000000000001",
    "error": "Connection timeout",
    "trace": "..."
  }
}
```

## Performance Considerations

### Asynchronous Processing

Currently, indexing happens synchronously in event subscribers. For high-volume scenarios, consider:

1. **Message Queue:**
```php
// Instead of direct indexing:
$this->productIndexer->indexProduct($product, $locale);

// Dispatch to message queue:
$this->messageBus->dispatch(new IndexProduct($product->id(), $locale));
```

2. **Bulk Operations:**
```php
// Index multiple products at once:
$this->productIndexer->indexProducts($products, $locale);
```

3. **Background Jobs:**
- Reindex entire catalog via console command
- Schedule periodic full reindexing (e.g., daily at 2 AM)

### Index Size Management

- Monitor index sizes per tenant
- Implement index rotation/archiving for old products
- Use Elasticsearch ILM (Index Lifecycle Management)

## Troubleshooting

### Product not appearing in search

1. Check if product is active:
```php
$product->isActive(); // Should be true
```

2. Check Elasticsearch index:
```bash
curl "http://elasticsearch:9200/products_*/_search?q=id:PRODUCT_ID"
```

3. Check event subscriber logs:
```bash
grep "Product indexed" var/log/dev.log
```

4. Manually reindex product:
```bash
symfony console app:search:reindex-product PRODUCT_ID
```

### Index out of sync

Full reindex command:
```bash
symfony console app:search:reindex-tenant TENANT_ID
```

## Future Enhancements

1. **Retry Queue:** Failed indexing operations should be retried
2. **Monitoring Dashboard:** Real-time indexing status
3. **A/B Testing:** Index different versions for search relevance tuning
4. **ML Integration:** Product embeddings for semantic search
5. **Analytics:** Track search performance metrics
6. **Auto-scaling:** Dynamic index sharding based on tenant size

## Related Components

- `ProductIndexer` - Handles actual Elasticsearch operations
- `IndexManager` - Manages index creation and configuration
- `ElasticsearchSearchService` - Search query execution
- `ProductRepository` - Domain model persistence
- `Catalog\Domain\Event\*` - Domain events from Catalog context

## References

- [Elasticsearch PHP Client Documentation](https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html)
- [Symfony Event Dispatcher](https://symfony.com/doc/current/event_dispatcher.html)
- [DDD Event-Driven Architecture](https://docs.google.com/document/d/ARCHITECTURE.md)
