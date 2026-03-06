<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Event;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryTranslationsUpdated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Event\OptionDefined;
use App\Catalog\Domain\Event\OptionRemoved;
use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeactivated;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductPublished;
use App\Catalog\Domain\Event\ProductReactivated;
use App\Catalog\Domain\Event\ProductTranslationsUpdated;
use App\Catalog\Domain\Event\ProductTypeChanged;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Event\SubscriptionConfigured;
use App\Catalog\Domain\Event\SubscriptionRemoved;
use App\Catalog\Domain\Event\SubscriptionUpdated;
use App\Catalog\Domain\Event\VariantsGenerated;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Domain\ValueObject\Subscription;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductDeactivated::class)]
#[CoversClass(ProductDiscontinued::class)]
#[CoversClass(ProductPublished::class)]
#[CoversClass(ProductReactivated::class)]
#[CoversClass(ProductCreated::class)]
#[CoversClass(ProductUpdated::class)]
#[CoversClass(CategoryCreated::class)]
#[CoversClass(CategoryUpdated::class)]
#[CoversClass(ProductTypeChanged::class)]
#[CoversClass(CategoryTranslationsUpdated::class)]
#[CoversClass(ProductTranslationsUpdated::class)]
#[CoversClass(OptionDefined::class)]
#[CoversClass(OptionRemoved::class)]
#[CoversClass(SubscriptionConfigured::class)]
#[CoversClass(SubscriptionRemoved::class)]
#[CoversClass(SubscriptionUpdated::class)]
#[CoversClass(VariantsGenerated::class)]
final class CatalogDomainEventsTest extends TestCase
{
    private ProductId $productId;
    private CategoryId $categoryId;
    private TenantId $tenantId;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->productId = ProductId::generate();
        $this->categoryId = CategoryId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->now = new \DateTimeImmutable();
    }

    // -------------------------------------------------------
    // Simple product lifecycle events
    // -------------------------------------------------------

    #[Test]
    public function productDeactivatedEvent(): void
    {
        $event = new ProductDeactivated($this->productId, $this->tenantId, $this->now);

        self::assertSame($this->productId, $event->productId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($this->now, $event->occurredAt);
    }

    #[Test]
    public function productDiscontinuedEvent(): void
    {
        $event = new ProductDiscontinued($this->productId, $this->tenantId, $this->now);

        self::assertSame($this->productId, $event->productId);
        self::assertSame($this->tenantId, $event->tenantId);
    }

    #[Test]
    public function productPublishedEvent(): void
    {
        $event = new ProductPublished($this->productId, $this->tenantId, $this->now);

        self::assertSame($this->productId, $event->productId);
    }

    #[Test]
    public function productReactivatedEvent(): void
    {
        $event = new ProductReactivated($this->productId, $this->tenantId, $this->now);

        self::assertSame($this->productId, $event->productId);
    }

    #[Test]
    public function productCreatedEvent(): void
    {
        $sku = SKU::fromString('PRD-123456');
        $event = new ProductCreated($this->productId, $this->tenantId, $sku, 'Test Product');

        self::assertSame($this->productId, $event->productId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($sku, $event->sku);
        self::assertSame('Test Product', $event->name);
    }

    #[Test]
    public function productUpdatedEvent(): void
    {
        $event = new ProductUpdated($this->productId, $this->tenantId);

        self::assertSame($this->productId, $event->productId);
        self::assertSame($this->tenantId, $event->tenantId);
    }

    #[Test]
    public function categoryCreatedEvent(): void
    {
        $event = new CategoryCreated($this->categoryId, $this->tenantId, 'Electronics');

        self::assertSame($this->categoryId, $event->categoryId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame('Electronics', $event->name);
    }

    #[Test]
    public function categoryUpdatedEvent(): void
    {
        $event = new CategoryUpdated($this->categoryId, $this->tenantId);

        self::assertSame($this->categoryId, $event->categoryId);
        self::assertSame($this->tenantId, $event->tenantId);
    }

    #[Test]
    public function productTypeChangedEvent(): void
    {
        $oldType = ProductType::simple();
        $newType = ProductType::bundle();
        $event = new ProductTypeChanged($this->productId, $this->tenantId, $oldType, $newType, $this->now);

        self::assertSame($this->productId, $event->productId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($oldType, $event->oldType);
        self::assertSame($newType, $event->newType);
        self::assertSame($this->now, $event->occurredAt);
    }

    // -------------------------------------------------------
    // Translation events
    // -------------------------------------------------------

    #[Test]
    public function categoryTranslationsUpdatedEvent(): void
    {
        $locale = Locale::fromString('en');
        $event = new CategoryTranslationsUpdated(
            $this->categoryId,
            $this->tenantId,
            $locale,
        );

        self::assertTrue($this->categoryId->equals($event->categoryId()));
        self::assertSame($this->tenantId, $event->tenantId());
        self::assertTrue($locale->equals($event->locale()));
        self::assertSame('catalog.category.translations_updated', $event->getEventName());
        self::assertIsArray($event->toArray());
    }

    #[Test]
    public function productTranslationsUpdatedEvent(): void
    {
        $locale = Locale::fromString('fr');
        $event = new ProductTranslationsUpdated(
            $this->productId,
            $this->tenantId,
            $locale,
        );

        self::assertSame($this->productId, $event->productId());
        self::assertSame($this->tenantId, $event->tenantId());
        self::assertTrue($locale->equals($event->locale()));
        self::assertSame('catalog.product.translations_updated', $event->getEventName());
        self::assertIsArray($event->toArray());
    }

    // -------------------------------------------------------
    // Option events
    // -------------------------------------------------------

    #[Test]
    public function optionDefinedEvent(): void
    {
        $configProdId = ConfigurableProductId::generate();
        $optionCode = OptionCode::fromString('color');
        $names = LocalizedString::fromArray(['en' => 'Color']);

        $event = new OptionDefined($configProdId, $this->productId, $optionCode, $names);

        self::assertSame($configProdId, $event->getConfigurableProductId());
        self::assertSame($this->productId, $event->getProductId());
        self::assertSame($optionCode, $event->getOptionCode());
        self::assertSame($names, $event->getNameTranslations());
        self::assertSame('catalog.option.defined', $event->getEventName());
        self::assertIsArray($event->toArray());
    }

    #[Test]
    public function optionRemovedEvent(): void
    {
        $configProdId = ConfigurableProductId::generate();
        $optionCode = OptionCode::fromString('size');

        $event = new OptionRemoved($configProdId, $this->productId, $optionCode, $this->now);

        self::assertSame($configProdId, $event->getConfigurableProductId());
        self::assertSame($optionCode, $event->getOptionCode());
    }

    // -------------------------------------------------------
    // Subscription events
    // -------------------------------------------------------

    #[Test]
    public function subscriptionConfiguredEvent(): void
    {
        $event = new SubscriptionConfigured(
            $this->productId,
            $this->tenantId,
            SubscriptionInterval::MONTHLY,
            12,
            $this->now,
        );

        self::assertSame($this->productId, $event->productId());
        self::assertSame(SubscriptionInterval::MONTHLY, $event->interval());
        self::assertSame(12, $event->billingCycles());
        self::assertSame('catalog.subscription.configured', $event->eventName());

        $array = $event->toArray();
        self::assertSame('monthly', $array['interval']);
        self::assertSame(12, $array['billingCycles']);
        self::assertFalse($array['isInfinite']);
    }

    #[Test]
    public function subscriptionConfiguredInfiniteEvent(): void
    {
        $event = new SubscriptionConfigured(
            $this->productId,
            $this->tenantId,
            SubscriptionInterval::YEARLY,
            0, // 0 = infinite
            $this->now,
        );

        $array = $event->toArray();
        self::assertTrue($array['isInfinite']);
    }

    #[Test]
    public function subscriptionRemovedEvent(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::of('0.00', 'USD'),
        );
        $event = new SubscriptionRemoved(
            $this->productId,
            $this->tenantId,
            $subscription,
            $this->now,
        );

        self::assertSame($this->productId, $event->productId());
        self::assertSame('catalog.subscription.removed', $event->eventName());
        self::assertSame($subscription, $event->subscription());
    }

    #[Test]
    public function subscriptionUpdatedEvent(): void
    {
        $oldSubscription = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::of('0.00', 'USD'),
        );
        $newSubscription = Subscription::create(
            SubscriptionInterval::YEARLY,
            24,
            Money::of('0.00', 'USD'),
        );
        $event = new SubscriptionUpdated(
            $this->productId,
            $this->tenantId,
            $oldSubscription,
            $newSubscription,
            $this->now,
        );

        self::assertSame($this->productId, $event->productId());
        self::assertSame($oldSubscription, $event->oldSubscription());
        self::assertSame($newSubscription, $event->newSubscription());
        self::assertSame('catalog.subscription.updated', $event->eventName());
    }

    // -------------------------------------------------------
    // Variants generated event
    // -------------------------------------------------------

    #[Test]
    public function variantsGeneratedEvent(): void
    {
        $configProdId = ConfigurableProductId::generate();

        $event = new VariantsGenerated($configProdId, $this->productId, 6);

        self::assertSame($configProdId, $event->getConfigurableProductId());
        self::assertSame($this->productId, $event->getProductId());
        self::assertSame(6, $event->getVariantsCount());
        self::assertSame('catalog.variants.generated', $event->getEventName());

        $array = $event->toArray();
        self::assertSame(6, $array['variants_count']);
    }
}
