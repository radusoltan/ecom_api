<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\EventSubscriber;

use App\Catalog\Application\EventSubscriber\ProductCreatedSubscriber;
use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ProductCreatedSubscriber::class)]
final class ProductCreatedSubscriberTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private ProductIndexer&MockObject $productIndexer;
    private LoggerInterface&MockObject $logger;
    private ProductCreatedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productIndexer = $this->createMock(ProductIndexer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new ProductCreatedSubscriber(
            productRepository: $this->productRepository,
            productIndexer: $this->productIndexer,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToProductCreatedEvent(): void
    {
        $events = ProductCreatedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ProductCreated::class, $events);
        self::assertSame('onProductCreated', $events[ProductCreated::class]);
    }

    #[Test]
    public function itReturnsExactlyOneSubscribedEvent(): void
    {
        $events = ProductCreatedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // Happy path: product found and indexed
    // -------------------------------------------------------

    #[Test]
    public function itIndexesProductInAllEnabledLocalesWhenProductFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductCreated(
            productId: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('PRD-123456'),
            name: 'Test Product',
        );

        $this->productRepository
            ->expects(self::once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        // Should be called once per locale — currently 2 locales (en_US, ro_RO)
        $this->productIndexer
            ->expects(self::exactly(2))
            ->method('indexProduct')
            ->with(
                self::identicalTo($product),
                self::isInstanceOf(Locale::class),
            );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Product indexed in Elasticsearch', self::anything());

        $this->subscriber->onProductCreated($event);
    }

    #[Test]
    public function itCallsIndexProductWithCorrectLocales(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductCreated(
            productId: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('ABC-000001'),
            name: 'My Product',
        );

        $this->productRepository->method('findById')->willReturn($product);

        $indexedLocales = [];
        $this->productIndexer
            ->expects(self::exactly(2))
            ->method('indexProduct')
            ->willReturnCallback(function (Product $p, Locale $locale) use (&$indexedLocales): void {
                $indexedLocales[] = $locale->toString();
            });

        $this->subscriber->onProductCreated($event);

        self::assertContains('en_US', $indexedLocales);
        self::assertContains('ro_RO', $indexedLocales);
    }

    // -------------------------------------------------------
    // Product not found: logs warning, no indexing
    // -------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenProductNotFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new ProductCreated(
            productId: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('XYZ-999999'),
            name: 'Ghost Product',
        );

        $this->productRepository
            ->method('findById')
            ->willReturn(null);

        $this->productIndexer
            ->expects(self::never())
            ->method('indexProduct');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Product not found for indexing',
                self::callback(fn (array $ctx): bool => $ctx['product_id'] === $productId->toString()),
            );

        $this->subscriber->onProductCreated($event);
    }

    #[Test]
    public function itDoesNotCallInfoLogWhenProductNotFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new ProductCreated(
            productId: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('ABC-000001'),
            name: 'Missing',
        );

        $this->productRepository->method('findById')->willReturn(null);

        $this->logger->expects(self::never())->method('info');

        $this->subscriber->onProductCreated($event);
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

        $event = new ProductCreated(
            productId: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('ABC-000001'),
            name: 'Failing Product',
        );

        $this->productRepository->method('findById')->willReturn($product);
        $this->productIndexer
            ->method('indexProduct')
            ->willThrowException(new \RuntimeException('Elasticsearch is down'));

        // Must not throw
        $this->subscriber->onProductCreated($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenIndexerFails(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($tenantId);

        $event = new ProductCreated(
            productId: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('ABC-000001'),
            name: 'Failing Product',
        );

        $this->productRepository->method('findById')->willReturn($product);
        $this->productIndexer
            ->method('indexProduct')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to index product',
                self::callback(fn (array $ctx): bool => $ctx['product_id'] === $productId->toString()
                    && 'Connection refused' === $ctx['error']
                ),
            );

        $this->subscriber->onProductCreated($event);
    }
}
