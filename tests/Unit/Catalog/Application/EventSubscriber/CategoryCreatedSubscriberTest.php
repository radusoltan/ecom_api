<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\EventSubscriber;

use App\Catalog\Application\EventSubscriber\CategoryCreatedSubscriber;
use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\CategoryIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CategoryCreatedSubscriber::class)]
final class CategoryCreatedSubscriberTest extends TestCase
{
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    private CategoryIndexer&MockObject $categoryIndexer;
    private LoggerInterface&MockObject $logger;
    private CategoryCreatedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->categoryIndexer = $this->createMock(CategoryIndexer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new CategoryCreatedSubscriber(
            categoryRepository: $this->categoryRepository,
            categoryIndexer: $this->categoryIndexer,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToCategoryCreatedEvent(): void
    {
        $events = CategoryCreatedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(CategoryCreated::class, $events);
        self::assertSame('onCategoryCreated', $events[CategoryCreated::class]);
    }

    #[Test]
    public function itReturnsExactlyOneSubscribedEvent(): void
    {
        $events = CategoryCreatedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // Happy path: category found and indexed
    // -------------------------------------------------------

    #[Test]
    public function itIndexesCategoryInAllEnabledLocalesWhenCategoryFound(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryCreated(
            categoryId: $categoryId,
            tenantId: $tenantId,
            name: 'Electronics',
        );

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        // Should be called once per locale — currently 2 locales (en_US, ro_RO)
        $this->categoryIndexer
            ->expects(self::exactly(2))
            ->method('indexCategory')
            ->with(
                self::identicalTo($category),
                self::isInstanceOf(Locale::class),
            );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Category indexed in Elasticsearch', self::anything());

        $this->subscriber->onCategoryCreated($event);
    }

    #[Test]
    public function itCallsIndexCategoryWithCorrectLocales(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryCreated(
            categoryId: $categoryId,
            tenantId: $tenantId,
            name: 'Books',
        );

        $this->categoryRepository->method('findById')->willReturn($category);

        $indexedLocales = [];
        $this->categoryIndexer
            ->expects(self::exactly(2))
            ->method('indexCategory')
            ->willReturnCallback(function (Category $c, Locale $locale) use (&$indexedLocales): void {
                $indexedLocales[] = $locale->toString();
            });

        $this->subscriber->onCategoryCreated($event);

        self::assertContains('en_US', $indexedLocales);
        self::assertContains('ro_RO', $indexedLocales);
    }

    #[Test]
    public function itLogsInfoWithCategoryIdAndLocalesAfterIndex(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryCreated(categoryId: $categoryId, tenantId: $tenantId, name: 'Clothing');

        $this->categoryRepository->method('findById')->willReturn($category);
        $this->categoryIndexer->method('indexCategory');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Category indexed in Elasticsearch',
                self::callback(fn (array $ctx): bool => $ctx['category_id'] === $categoryId->toString()
                    && isset($ctx['locales'])
                ),
            );

        $this->subscriber->onCategoryCreated($event);
    }

    // -------------------------------------------------------
    // Category not found: logs warning, no indexing
    // -------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenCategoryNotFound(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new CategoryCreated(
            categoryId: $categoryId,
            tenantId: $tenantId,
            name: 'Ghost Category',
        );

        $this->categoryRepository->method('findById')->willReturn(null);

        $this->categoryIndexer->expects(self::never())->method('indexCategory');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Category not found for indexing',
                self::callback(fn (array $ctx): bool => $ctx['category_id'] === $categoryId->toString()),
            );

        $this->subscriber->onCategoryCreated($event);
    }

    #[Test]
    public function itDoesNotCallInfoLogWhenCategoryNotFound(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new CategoryCreated(categoryId: $categoryId, tenantId: $tenantId, name: 'Missing');

        $this->categoryRepository->method('findById')->willReturn(null);

        $this->logger->expects(self::never())->method('info');

        $this->subscriber->onCategoryCreated($event);
    }

    // -------------------------------------------------------
    // Error handling: indexer throws, no exception propagated
    // -------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenIndexerFails(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryCreated(categoryId: $categoryId, tenantId: $tenantId, name: 'Failing');

        $this->categoryRepository->method('findById')->willReturn($category);
        $this->categoryIndexer
            ->method('indexCategory')
            ->willThrowException(new \RuntimeException('Elasticsearch is down'));

        // Must not throw
        $this->subscriber->onCategoryCreated($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenIndexerFails(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryCreated(categoryId: $categoryId, tenantId: $tenantId, name: 'Failing');

        $this->categoryRepository->method('findById')->willReturn($category);
        $this->categoryIndexer
            ->method('indexCategory')
            ->willThrowException(new \RuntimeException('Cluster health red'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to index category',
                self::callback(fn (array $ctx): bool => $ctx['category_id'] === $categoryId->toString()
                    && 'Cluster health red' === $ctx['error']
                ),
            );

        $this->subscriber->onCategoryCreated($event);
    }
}
