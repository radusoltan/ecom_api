<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application\EventSubscriber;

use App\Search\Infrastructure\EventSubscriber\TenantIndexLifecycleSubscriber;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\Event\TenantDeactivated;
use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TenantIndexLifecycleSubscriberTest extends TestCase
{
    private IndexManager $indexManager;
    private LoggerInterface $logger;
    private TenantIndexLifecycleSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->indexManager = $this->createMock(IndexManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new TenantIndexLifecycleSubscriber(
            $this->indexManager,
            $this->logger,
        );
    }

    public function testItSubscribesToTenantEvents(): void
    {
        $events = TenantIndexLifecycleSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(TenantCreated::class, $events);
        self::assertArrayHasKey(TenantDeactivated::class, $events);
    }

    public function testItCreatesIndicesOnTenantCreated(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new TenantCreated(
            tenantId: $tenantId,
            name: TenantName::fromString('Test Tenant'),
            ownerEmail: Email::fromString('owner@test.com'),
        );

        // 6 locales × 1 product index + 6 locales × 1 category index = 12 calls
        $this->indexManager->expects(self::exactly(6))->method('createProductIndex');
        $this->indexManager->expects(self::exactly(6))->method('createCategoryIndex');

        $this->subscriber->onTenantCreated($event);
    }

    public function testItDeletesIndicesOnTenantDeactivated(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new TenantDeactivated(tenantId: $tenantId);

        $this->indexManager->method('getProductIndexName')->willReturn('test_products_enus');
        $this->indexManager->method('getCategoryIndexName')->willReturn('test_categories_enus');

        // 6 locales × 2 index types = 12 delete calls
        $this->indexManager->expects(self::exactly(12))->method('deleteIndex');

        $this->subscriber->onTenantDeactivated($event);
    }

    public function testItLogsErrorButContinuesOnIndexCreationFailure(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new TenantCreated(
            tenantId: $tenantId,
            name: TenantName::fromString('Test Tenant'),
            ownerEmail: Email::fromString('owner@test.com'),
        );

        $this->indexManager->method('createProductIndex')
            ->willThrowException(new \RuntimeException('ES unavailable'));

        // Should still attempt category indices despite product index failures
        $this->logger->expects(self::atLeastOnce())->method('error');
        $this->logger->expects(self::once())->method('info');

        $this->subscriber->onTenantCreated($event);
    }
}
