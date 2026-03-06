<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\EventSubscriber;

use App\Catalog\Application\EventSubscriber\CategoryUpdatedSubscriber;
use App\Catalog\Domain\Event\CategoryUpdated;
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

#[CoversClass(CategoryUpdatedSubscriber::class)]
final class CategoryUpdatedSubscriberTest extends TestCase
{
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    private CategoryIndexer&MockObject $categoryIndexer;
    private LoggerInterface&MockObject $logger;
    private CategoryUpdatedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->categoryIndexer = $this->createMock(CategoryIndexer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new CategoryUpdatedSubscriber(
            categoryRepository: $this->categoryRepository,
            categoryIndexer: $this->categoryIndexer,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToCategoryUpdatedEvent(): void
    {
        $events = CategoryUpdatedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(CategoryUpdated::class, $events);
        self::assertSame('onCategoryUpdated', $events[CategoryUpdated::class]);
    }

    #[Test]
    public function itReturnsExactlyOneSubscribedEvent(): void
    {
        $events = CategoryUpdatedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // Happy path: category found and reindexed
    // -------------------------------------------------------

    #[Test]
    public function itReindexesCategoryInAllEnabledLocalesWhenCategoryFound(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryUpdated(
            categoryId: $categoryId,
            tenantId: $tenantId,
        );

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        // Should be called once per locale — currently 2 locales (en_US, ro_RO)
        $this->categoryIndexer
            ->expects(self::exactly(2))
            ->method('updateCategory')
            ->with(
                self::identicalTo($category),
                self::isInstanceOf(Locale::class),
            );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Category reindexed in Elasticsearch', self::anything());

        $this->subscriber->onCategoryUpdated($event);
    }

    #[Test]
    public function itCallsUpdateCategoryWithCorrectLocales(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryUpdated(categoryId: $categoryId, tenantId: $tenantId);

        $this->categoryRepository->method('findById')->willReturn($category);

        $updatedLocales = [];
        $this->categoryIndexer
            ->expects(self::exactly(2))
            ->method('updateCategory')
            ->willReturnCallback(function (Category $c, Locale $locale) use (&$updatedLocales): void {
                $updatedLocales[] = $locale->toString();
            });

        $this->subscriber->onCategoryUpdated($event);

        self::assertContains('en_US', $updatedLocales);
        self::assertContains('ro_RO', $updatedLocales);
    }

    #[Test]
    public function itLogsInfoWithCategoryIdAndLocalesAfterReindex(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryUpdated(categoryId: $categoryId, tenantId: $tenantId);

        $this->categoryRepository->method('findById')->willReturn($category);
        $this->categoryIndexer->method('updateCategory');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Category reindexed in Elasticsearch',
                self::callback(fn (array $ctx): bool => $ctx['category_id'] === $categoryId->toString()
                    && isset($ctx['locales'])
                ),
            );

        $this->subscriber->onCategoryUpdated($event);
    }

    // -------------------------------------------------------
    // Category not found: logs warning, no reindexing
    // -------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenCategoryNotFound(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new CategoryUpdated(categoryId: $categoryId, tenantId: $tenantId);

        $this->categoryRepository->method('findById')->willReturn(null);

        $this->categoryIndexer->expects(self::never())->method('updateCategory');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Category not found for reindexing',
                self::callback(fn (array $ctx): bool => $ctx['category_id'] === $categoryId->toString()),
            );

        $this->subscriber->onCategoryUpdated($event);
    }

    #[Test]
    public function itDoesNotCallInfoLogWhenCategoryNotFound(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new CategoryUpdated(categoryId: $categoryId, tenantId: $tenantId);

        $this->categoryRepository->method('findById')->willReturn(null);

        $this->logger->expects(self::never())->method('info');

        $this->subscriber->onCategoryUpdated($event);
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

        $event = new CategoryUpdated(categoryId: $categoryId, tenantId: $tenantId);

        $this->categoryRepository->method('findById')->willReturn($category);
        $this->categoryIndexer
            ->method('updateCategory')
            ->willThrowException(new \RuntimeException('Elasticsearch timeout'));

        // Must not throw
        $this->subscriber->onCategoryUpdated($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenIndexerFails(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createMock(Category::class);
        $category->method('tenantId')->willReturn($tenantId);

        $event = new CategoryUpdated(categoryId: $categoryId, tenantId: $tenantId);

        $this->categoryRepository->method('findById')->willReturn($category);
        $this->categoryIndexer
            ->method('updateCategory')
            ->willThrowException(new \RuntimeException('Node unavailable'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to reindex category',
                self::callback(fn (array $ctx): bool => $ctx['category_id'] === $categoryId->toString()
                    && 'Node unavailable' === $ctx['error']
                ),
            );

        $this->subscriber->onCategoryUpdated($event);
    }
}
