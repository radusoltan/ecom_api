<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application\EventSubscriber;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeactivated;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductReactivated;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Search\Application\EventSubscriber\ProductIndexSubscriber;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ProductIndexSubscriber::class)]
final class ProductIndexSubscriberTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private ProductIndexer $productIndexer;
    private LoggerInterface $logger;
    private ProductIndexSubscriber $subscriber;

    private TenantId $tenantId;
    private ProductId $productId;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productIndexer = $this->createMock(ProductIndexer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new ProductIndexSubscriber(
            productRepository: $this->productRepository,
            productIndexer: $this->productIndexer,
            logger: $this->logger,
        );

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->productId = ProductId::generate();
    }

    // -------------------------------------------------------
    // getSubscribedEvents
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToExpectedEvents(): void
    {
        $events = ProductIndexSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ProductCreated::class, $events);
        self::assertArrayHasKey(ProductUpdated::class, $events);
        self::assertArrayHasKey(ProductDeactivated::class, $events);
        self::assertArrayHasKey(ProductDiscontinued::class, $events);
        self::assertArrayHasKey(ProductReactivated::class, $events);
    }

    // -------------------------------------------------------
    // onProductCreated
    // -------------------------------------------------------

    #[Test]
    public function itIndexesProductOnCreatedEvent(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($this->tenantId);

        $this->productRepository
            ->expects(self::once())
            ->method('findById')
            ->with($this->productId)
            ->willReturn($product);

        // Called once per enabled locale (4 locales: en_US, fr_FR, de_DE, ro_RO)
        $this->productIndexer
            ->expects(self::exactly(4))
            ->method('indexProduct');

        $this->logger->expects(self::once())->method('info');

        $event = new ProductCreated(
            productId: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-123456'),
            name: 'Test Product',
        );

        $this->subscriber->onProductCreated($event);
    }

    #[Test]
    public function itLogsWarningWhenProductNotFoundOnCreated(): void
    {
        $this->productRepository->method('findById')->willReturn(null);
        $this->productIndexer->expects(self::never())->method('indexProduct');
        $this->logger->expects(self::once())->method('warning');

        $event = new ProductCreated(
            productId: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-123456'),
            name: 'Ghost Product',
        );

        $this->subscriber->onProductCreated($event);
    }

    #[Test]
    public function itLogsErrorAndDoesNotThrowWhenIndexingFails(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($this->tenantId);

        $this->productRepository->method('findById')->willReturn($product);

        $this->productIndexer
            ->method('indexProduct')
            ->willThrowException(new \RuntimeException('Elasticsearch unavailable'));

        $this->logger->expects(self::once())->method('error');

        $event = new ProductCreated(
            productId: $this->productId,
            tenantId: $this->tenantId,
            sku: SKU::fromString('PRD-123456'),
            name: 'Test',
        );

        // Must not throw - graceful error handling
        $this->subscriber->onProductCreated($event);
    }

    // -------------------------------------------------------
    // onProductUpdated
    // -------------------------------------------------------

    #[Test]
    public function itReindexesProductOnUpdatedEvent(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($this->tenantId);

        $this->productRepository->method('findById')->willReturn($product);

        $this->productIndexer
            ->expects(self::exactly(4))
            ->method('updateProduct');

        $this->logger->expects(self::once())->method('info');

        $this->subscriber->onProductUpdated(new ProductUpdated($this->productId, $this->tenantId));
    }

    #[Test]
    public function itLogsWarningWhenProductNotFoundOnUpdated(): void
    {
        $this->productRepository->method('findById')->willReturn(null);
        $this->productIndexer->expects(self::never())->method('updateProduct');
        $this->logger->expects(self::once())->method('warning');

        $this->subscriber->onProductUpdated(new ProductUpdated($this->productId, $this->tenantId));
    }

    // -------------------------------------------------------
    // onProductDeactivated
    // -------------------------------------------------------

    #[Test]
    public function itUpdatesIndexWhenProductDeactivated(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($this->tenantId);

        $this->productRepository->method('findById')->willReturn($product);

        $this->productIndexer
            ->expects(self::exactly(4))
            ->method('updateProduct');

        $this->logger->expects(self::once())->method('info');

        $this->subscriber->onProductDeactivated(
            new ProductDeactivated($this->productId, $this->tenantId, new \DateTimeImmutable()),
        );
    }

    // -------------------------------------------------------
    // onProductDiscontinued
    // -------------------------------------------------------

    #[Test]
    public function itDeletesFromIndexWhenProductDiscontinued(): void
    {
        $this->productIndexer
            ->expects(self::exactly(4))
            ->method('deleteProduct')
            ->with($this->productId, $this->tenantId);

        $this->logger->expects(self::once())->method('info');

        $this->subscriber->onProductDiscontinued(
            new ProductDiscontinued($this->productId, $this->tenantId, new \DateTimeImmutable()),
        );
    }

    #[Test]
    public function itLogsErrorWhenDeleteFails(): void
    {
        $this->productIndexer
            ->method('deleteProduct')
            ->willThrowException(new \RuntimeException('ES error'));

        $this->logger->expects(self::once())->method('error');

        $this->subscriber->onProductDiscontinued(
            new ProductDiscontinued($this->productId, $this->tenantId, new \DateTimeImmutable()),
        );
    }

    // -------------------------------------------------------
    // onProductReactivated
    // -------------------------------------------------------

    #[Test]
    public function itReindexesProductOnReactivatedEvent(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($this->tenantId);

        $this->productRepository->method('findById')->willReturn($product);

        $this->productIndexer
            ->expects(self::exactly(4))
            ->method('indexProduct');

        $this->logger->expects(self::once())->method('info');

        $this->subscriber->onProductReactivated(
            new ProductReactivated($this->productId, $this->tenantId, new \DateTimeImmutable()),
        );
    }

    #[Test]
    public function itLogsWarningWhenProductNotFoundOnReactivated(): void
    {
        $this->productRepository->method('findById')->willReturn(null);
        $this->productIndexer->expects(self::never())->method('indexProduct');
        $this->logger->expects(self::once())->method('warning');

        $this->subscriber->onProductReactivated(
            new ProductReactivated($this->productId, $this->tenantId, new \DateTimeImmutable()),
        );
    }
}
