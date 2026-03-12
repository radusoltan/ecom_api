<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\EventSubscriber;

use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use App\Shared\Infrastructure\EventSubscriber\CacheInvalidationSubscriber;
use App\Tenant\Domain\Event\TenantDeactivated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[CoversClass(CacheInvalidationSubscriber::class)]
final class CacheInvalidationSubscriberTest extends TestCase
{
    private TagAwareCacheInterface $cache;
    private CacheInvalidationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->subscriber = new CacheInvalidationSubscriber(
            new CacheService($this->cache, new NullLogger()),
            new NullLogger()
        );
    }

    #[Test]
    public function itInvalidatesTenantScopedProductTagsOnProductUpdate(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $productId = ProductId::fromString('11111111-1111-4111-8111-111111111111');

        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with([
                'tenant.00000000-0000-4000-8000-000000000001.products',
                'product.11111111-1111-4111-8111-111111111111',
            ])
            ->willReturn(true);

        $this->subscriber->onProductUpdated(new ProductUpdated($productId, $tenantId));
    }

    #[Test]
    public function itInvalidatesEntireTenantNamespaceWhenTenantIsDeactivated(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant.00000000-0000-4000-8000-000000000001'])
            ->willReturn(true);

        $this->subscriber->onTenantDeactivated(new TenantDeactivated($tenantId));
    }
}
