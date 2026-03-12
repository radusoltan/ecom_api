<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Event;

use App\Pricing\Domain\Event\DiscountsStacked;
use App\Pricing\Domain\Event\PriceListActivated;
use App\Pricing\Domain\Event\PriceListCreated;
use App\Pricing\Domain\Event\PriceListDeactivated;
use App\Pricing\Domain\Event\PromotionActivated;
use App\Pricing\Domain\Event\PromotionCreated;
use App\Pricing\Domain\Event\PromotionDeactivated;
use App\Pricing\Domain\Event\PromotionUpdated;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiscountsStacked::class)]
#[CoversClass(PriceListCreated::class)]
#[CoversClass(PriceListActivated::class)]
#[CoversClass(PriceListDeactivated::class)]
#[CoversClass(PromotionCreated::class)]
#[CoversClass(PromotionActivated::class)]
#[CoversClass(PromotionDeactivated::class)]
#[CoversClass(PromotionUpdated::class)]
final class PricingDomainEventsTest extends TestCase
{
    private TenantId $tenantId;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->now = new \DateTimeImmutable();
    }

    #[Test]
    public function discountsStackedEvent(): void
    {
        $promos = [
            ['promotionId' => 'p1', 'name' => 'Summer', 'type' => 'cart_rule', 'discountAmount' => 500, 'priceAfterDiscount' => 4500],
            ['promotionId' => 'p2', 'name' => 'VIP', 'type' => 'catalog_rule', 'discountAmount' => 200, 'priceAfterDiscount' => 4300],
        ];

        $event = new DiscountsStacked(
            $this->tenantId,
            $promos,
            5000,
            4300,
            700,
            'USD',
            $this->now,
        );

        self::assertSame($this->tenantId, $event->tenantId());
        self::assertCount(2, $event->appliedPromotions());
        self::assertSame(5000, $event->originalAmount());
        self::assertSame(4300, $event->finalAmount());
        self::assertSame(700, $event->totalDiscountAmount());
        self::assertSame('USD', $event->currency());
        self::assertSame($this->now, $event->occurredAt());
        self::assertSame($this->now, $event->occurredOn());
        self::assertSame('pricing.discounts.stacked', $event->getEventName());

        $array = $event->toArray();
        self::assertSame(5000, $array['original_amount']);
        self::assertSame(700, $array['total_discount_amount']);
        self::assertSame('USD', $array['currency']);
    }

    #[Test]
    public function priceListCreatedEvent(): void
    {
        $event = new PriceListCreated('pl-1', 't-1', 'Summer Sale', 100, $this->now);

        self::assertSame('pl-1', $event->priceListId());
        self::assertSame('t-1', $event->tenantId());
        self::assertSame('Summer Sale', $event->name());
        self::assertSame(100, $event->priority());
        self::assertSame($this->now, $event->occurredOn());

        $array = $event->toArray();
        self::assertSame('pl-1', $array['price_list_id']);
        self::assertSame('Summer Sale', $array['name']);
    }

    #[Test]
    public function promotionCreatedEvent(): void
    {
        $promoId = PromotionId::generate();
        $type = PromotionType::fromString('coupon');

        $event = new PromotionCreated($promoId, $this->tenantId, 'Welcome', $type, $this->now);

        self::assertSame($promoId, $event->promotionId());
        self::assertSame($this->tenantId, $event->tenantId());
        self::assertSame('Welcome', $event->name());
        self::assertSame($type, $event->type());
        self::assertSame($this->now, $event->occurredAt());
        self::assertSame($this->now, $event->occurredOn());
    }

    #[Test]
    public function priceListActivatedEvent(): void
    {
        $event = new PriceListActivated('pl-1', 't-1', $this->now);

        self::assertSame('pl-1', $event->priceListId());
        self::assertSame('t-1', $event->tenantId());
        self::assertSame($this->now, $event->occurredOn());

        $array = $event->toArray();
        self::assertSame('pl-1', $array['price_list_id']);
        self::assertSame('t-1', $array['tenant_id']);
    }

    #[Test]
    public function priceListDeactivatedEvent(): void
    {
        $event = new PriceListDeactivated('pl-1', 't-1', $this->now);

        self::assertSame('pl-1', $event->priceListId());
        self::assertSame('t-1', $event->tenantId());

        $array = $event->toArray();
        self::assertSame('t-1', $array['tenant_id']);
    }

    #[Test]
    public function promotionActivatedEvent(): void
    {
        $promoId = PromotionId::generate();
        $event = new PromotionActivated($promoId, $this->tenantId, $this->now);

        self::assertSame($promoId, $event->promotionId());
        self::assertSame($this->tenantId, $event->tenantId());
        self::assertSame($this->now, $event->occurredAt());
    }

    #[Test]
    public function promotionDeactivatedEvent(): void
    {
        $promoId = PromotionId::generate();
        $event = new PromotionDeactivated($promoId, $this->tenantId, $this->now);

        self::assertSame($promoId, $event->promotionId());
        self::assertSame($this->tenantId, $event->tenantId());
    }

    #[Test]
    public function promotionUpdatedEvent(): void
    {
        $promoId = PromotionId::generate();
        $event = new PromotionUpdated($promoId, $this->tenantId, $this->now);

        self::assertSame($promoId, $event->promotionId());
        self::assertSame($this->tenantId, $event->tenantId());
        self::assertSame($this->now, $event->occurredAt());
    }
}
