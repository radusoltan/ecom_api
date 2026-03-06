<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\EventSubscriber;

use App\Catalog\Application\EventSubscriber\ProductUpdatedSubscriber;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ProductUpdatedSubscriber::class)]
final class ProductUpdatedSubscriberTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private ProductIndexer&MockObject $productIndexer;
    private LoggerInterface&MockObject $logger;
    private ProductUpdatedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productIndexer = $this->createMock(ProductIndexer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new ProductUpdatedSubscriber(
            productRepository: $this->productRepository,
            productIndexer: $this->productIndexer,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToProductUpdatedEvent(): void
    {
        $events = ProductUpdatedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ProductUpdated::class, $events);
        self::assertSame('onProductUpdated', $events[ProductUpdated::class]);
    }

    #[Test]
    public function itReturnsExactlyOneSubscribedEvent(): void
    {
        $events = ProductUpdatedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // Happy path: product found and reindexed
    // -------------------------------------------------------

    #[Test]
    public function itReindexesProductInAllEnabledLocalesWhenProductFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductUpdated(
            productId: $productId,
            tenantId: $tenantId,
        );

        $this->productRepository
            ->expects(self::once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        // Should be called once per locale — currently 2 locales (en_US, ro_RO)
        $this->productIndexer
            ->expects(self::exactly(2))
            ->method('updateProduct')
            ->with(
                self::identicalTo($product),
                self::isInstanceOf(Locale::class),
            );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Product reindexed in Elasticsearch', self::anything());

        $this->subscriber->onProductUpdated($event);
    }

    #[Test]
    public function itCallsUpdateProductWithCorrectLocales(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductUpdated(
            productId: $productId,
            tenantId: $tenantId,
        );

        $this->productRepository->method('findById')->willReturn($product);

        $updatedLocales = [];
        $this->productIndexer
            ->expects(self::exactly(2))
            ->method('updateProduct')
            ->willReturnCallback(function (Product $p, Locale $locale) use (&$updatedLocales): void {
                $updatedLocales[] = $locale->toString();
            });

        $this->subscriber->onProductUpdated($event);

        self::assertContains('en_US', $updatedLocales);
        self::assertContains('ro_RO', $updatedLocales);
    }

    #[Test]
    public function itLogsInfoWithProductIdAndLocalesAfterReindex(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductUpdated(productId: $productId, tenantId: $tenantId);

        $this->productRepository->method('findById')->willReturn($product);
        $this->productIndexer->method('updateProduct');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Product reindexed in Elasticsearch',
                self::callback(fn (array $ctx): bool => $ctx['product_id'] === $productId->toString()
                    && isset($ctx['locales'])
                ),
            );

        $this->subscriber->onProductUpdated($event);
    }

    // -------------------------------------------------------
    // Product not found: logs warning, no indexing
    // -------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenProductNotFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new ProductUpdated(productId: $productId, tenantId: $tenantId);

        $this->productRepository->method('findById')->willReturn(null);

        $this->productIndexer->expects(self::never())->method('updateProduct');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Product not found for reindexing',
                self::callback(fn (array $ctx): bool => $ctx['product_id'] === $productId->toString()),
            );

        $this->subscriber->onProductUpdated($event);
    }

    // -------------------------------------------------------
    // Error handling: indexer throws, no exception propagated
    // -------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenIndexerFails(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductUpdated(productId: $productId, tenantId: $tenantId);

        $this->productRepository->method('findById')->willReturn($product);
        $this->productIndexer
            ->method('updateProduct')
            ->willThrowException(new \RuntimeException('Elasticsearch unavailable'));

        // Must not throw
        $this->subscriber->onProductUpdated($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenIndexerFails(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductUpdated(productId: $productId, tenantId: $tenantId);

        $this->productRepository->method('findById')->willReturn($product);
        $this->productIndexer
            ->method('updateProduct')
            ->willThrowException(new \RuntimeException('Index not found'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to reindex product',
                self::callback(fn (array $ctx): bool => $ctx['product_id'] === $productId->toString()
                    && 'Index not found' === $ctx['error']
                ),
            );

        $this->subscriber->onProductUpdated($event);
    }
}
