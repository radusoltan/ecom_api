<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\EventSubscriber;

use App\Pricing\Domain\Event\PriceListActivated;
use App\Pricing\Domain\Event\PriceListDeactivated;
use App\Pricing\Domain\Event\PromotionActivated;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Infrastructure\EventSubscriber\PricingCacheInvalidationSubscriber;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[CoversClass(PricingCacheInvalidationSubscriber::class)]
final class PricingCacheInvalidationSubscriberTest extends TestCase
{
    private TagAwareCacheInterface $cache;
    private PricingCacheInvalidationSubscriber $subscriber;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->subscriber = new PricingCacheInvalidationSubscriber(
            new CacheService($this->cache, new NullLogger()),
            new NullLogger()
        );
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    #[Test]
    public function itInvalidatesTenantScopedPriceListTagsWhenActivated(): void
    {
        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant.00000000-0000-4000-8000-000000000001.price_lists'])
            ->willReturn(true);

        $this->subscriber->onPriceListActivated(new PriceListActivated(
            'pl-1',
            $this->tenantId->toString(),
            new \DateTimeImmutable('2026-03-10T12:00:00+00:00')
        ));
    }

    #[Test]
    public function itInvalidatesTenantScopedPriceListTagsWhenDeactivated(): void
    {
        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant.00000000-0000-4000-8000-000000000001.price_lists'])
            ->willReturn(true);

        $this->subscriber->onPriceListDeactivated(new PriceListDeactivated(
            'pl-1',
            $this->tenantId->toString(),
            new \DateTimeImmutable('2026-03-10T12:30:00+00:00')
        ));
    }

    #[Test]
    public function itInvalidatesPromotionAndProductCachesForActivatedPromotion(): void
    {
        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with([
                'tenant.00000000-0000-4000-8000-000000000001.promotions',
                'tenant.00000000-0000-4000-8000-000000000001.products',
            ])
            ->willReturn(true);

        $this->subscriber->onPromotionActivated(new PromotionActivated(
            PromotionId::fromString('22222222-2222-4222-8222-222222222222'),
            $this->tenantId,
            new \DateTimeImmutable('2026-03-10T13:00:00+00:00')
        ));
    }
}
