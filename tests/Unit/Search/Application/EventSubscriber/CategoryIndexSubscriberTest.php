<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application\EventSubscriber;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Search\Application\EventSubscriber\CategoryIndexSubscriber;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[CoversClass(CategoryIndexSubscriber::class)]
final class CategoryIndexSubscriberTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private ProductIndexer&MockObject $productIndexer;
    private LoggerInterface&MockObject $logger;
    private CategoryIndexSubscriber $subscriber;

    private TenantId $tenantId;
    private CategoryId $categoryId;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productIndexer = $this->createMock(ProductIndexer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new CategoryIndexSubscriber(
            productRepository: $this->productRepository,
            productIndexer: $this->productIndexer,
            logger: $this->logger,
        );

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->categoryId = CategoryId::generate();
    }

    // -----------------------------------------------------------------------
    // getSubscribedEvents
    // -----------------------------------------------------------------------

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itSubscribesToCategoryCreatedAndCategoryUpdated(): void
    {
        $events = CategoryIndexSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(CategoryCreated::class, $events);
        self::assertArrayHasKey(CategoryUpdated::class, $events);
    }

    #[Test]
    public function itMapsEventsToCorrectHandlerMethods(): void
    {
        $events = CategoryIndexSubscriber::getSubscribedEvents();

        self::assertSame('onCategoryCreated', $events[CategoryCreated::class]);
        self::assertSame('onCategoryUpdated', $events[CategoryUpdated::class]);
    }

    #[Test]
    public function itSubscribesToExactlyTwoEvents(): void
    {
        $events = CategoryIndexSubscriber::getSubscribedEvents();

        self::assertCount(2, $events);
    }

    // -----------------------------------------------------------------------
    // onCategoryCreated
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoWhenCategoryCreated(): void
    {
        $event = new CategoryCreated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            name: 'Electronics',
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Category created - no reindexing needed',
                self::anything(),
            );

        $this->subscriber->onCategoryCreated($event);
    }

    #[Test]
    public function itDoesNotIndexAnyProductsWhenCategoryCreated(): void
    {
        $event = new CategoryCreated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            name: 'New Category',
        );

        // No products should be fetched or indexed for a newly created category
        $this->productRepository->expects(self::never())->method('findById');
        $this->productIndexer->expects(self::never())->method('indexProduct');
        $this->productIndexer->expects(self::never())->method('updateProduct');
        $this->productIndexer->expects(self::never())->method('deleteProduct');

        $this->logger->method('info');

        $this->subscriber->onCategoryCreated($event);
    }

    #[Test]
    public function itIncludesCategoryIdInCreatedLogContext(): void
    {
        $event = new CategoryCreated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            name: 'Clothing',
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('category_id', $context);
                    self::assertSame($this->categoryId->toString(), $context['category_id']);

                    return true;
                }),
            );

        $this->subscriber->onCategoryCreated($event);
    }

    #[Test]
    public function itIncludesTenantIdInCreatedLogContext(): void
    {
        $event = new CategoryCreated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            name: 'Shoes',
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('tenant_id', $context);
                    self::assertSame($this->tenantId->toString(), $context['tenant_id']);

                    return true;
                }),
            );

        $this->subscriber->onCategoryCreated($event);
    }

    #[Test]
    public function itIncludesNameInCreatedLogContext(): void
    {
        $categoryName = 'Home & Garden';
        $event = new CategoryCreated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            name: $categoryName,
        );

        $capturedContext = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onCategoryCreated($event);

        self::assertArrayHasKey('name', $capturedContext);
        self::assertSame($categoryName, $capturedContext['name']);
    }

    #[Test]
    public function itDoesNotThrowWhenCategoryCreated(): void
    {
        $event = new CategoryCreated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            name: 'Books',
        );

        $this->logger->method('info');

        // Must not throw
        $this->subscriber->onCategoryCreated($event);

        self::assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // onCategoryUpdated
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoWhenCategoryUpdated(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Category updated - products need reindexing',
                self::anything(),
            );

        $this->subscriber->onCategoryUpdated($event);
    }

    #[Test]
    public function itIncludesCategoryIdInUpdatedLogContext(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('category_id', $context);
                    self::assertSame($this->categoryId->toString(), $context['category_id']);

                    return true;
                }),
            );

        $this->subscriber->onCategoryUpdated($event);
    }

    #[Test]
    public function itIncludesTenantIdInUpdatedLogContext(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('tenant_id', $context);
                    self::assertSame($this->tenantId->toString(), $context['tenant_id']);

                    return true;
                }),
            );

        $this->subscriber->onCategoryUpdated($event);
    }

    #[Test]
    public function itIncludesNoteInUpdatedLogContext(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        $capturedContext = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onCategoryUpdated($event);

        self::assertArrayHasKey('note', $capturedContext);
        self::assertIsString($capturedContext['note']);
    }

    #[Test]
    public function itDoesNotThrowWhenCategoryUpdated(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        $this->logger->method('info');

        // Must not throw
        $this->subscriber->onCategoryUpdated($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itHandlesExceptionGracefullyOnCategoryUpdated(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        // Force logger to throw to simulate unexpected failure inside try block
        $this->logger
            ->method('info')
            ->willThrowException(new \RuntimeException('Logger unavailable'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to reindex products after category update',
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('category_id', $context);
                    self::assertArrayHasKey('tenant_id', $context);
                    self::assertArrayHasKey('error', $context);

                    return true;
                }),
            );

        // Must not throw — subscriber has graceful error handling
        $this->subscriber->onCategoryUpdated($event);
    }

    #[Test]
    public function itIncludesCategoryIdInErrorLogWhenUpdateFails(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        $this->logger->method('info')->willThrowException(new \RuntimeException('Unexpected error'));

        $capturedErrorContext = [];
        $this->logger
            ->method('error')
            ->willReturnCallback(
                function (string $message, array $context) use (&$capturedErrorContext): void {
                    $capturedErrorContext = $context;
                }
            );

        $this->subscriber->onCategoryUpdated($event);

        self::assertSame($this->categoryId->toString(), $capturedErrorContext['category_id']);
        self::assertSame($this->tenantId->toString(), $capturedErrorContext['tenant_id']);
    }

    // -----------------------------------------------------------------------
    // Context completeness
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogContextContainsAllRequiredKeysForCreated(): void
    {
        $event = new CategoryCreated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            name: 'Accessories',
        );

        $capturedContext = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onCategoryCreated($event);

        self::assertArrayHasKey('category_id', $capturedContext);
        self::assertArrayHasKey('tenant_id', $capturedContext);
        self::assertArrayHasKey('name', $capturedContext);
    }

    #[Test]
    public function itLogContextContainsAllRequiredKeysForUpdated(): void
    {
        $event = new CategoryUpdated(
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
        );

        $capturedContext = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onCategoryUpdated($event);

        self::assertArrayHasKey('category_id', $capturedContext);
        self::assertArrayHasKey('tenant_id', $capturedContext);
    }
}
