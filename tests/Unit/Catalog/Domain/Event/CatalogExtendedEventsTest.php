<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Event;

use App\Catalog\Domain\Event\BundleCreated;
use App\Catalog\Domain\Event\BundleItemAdded;
use App\Catalog\Domain\Event\BundleItemRemoved;
use App\Catalog\Domain\Event\BundleRemoved;
use App\Catalog\Domain\Event\BundleUpdated;
use App\Catalog\Domain\Event\DownloadableFileAttached;
use App\Catalog\Domain\Event\DownloadableFileRemoved;
use App\Catalog\Domain\Event\DownloadableFileUpdated;
use App\Catalog\Domain\Event\VariantAdded;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Bundle;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Catalog\Domain\ValueObject\DownloadableFile;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadableFileAttached::class)]
#[CoversClass(DownloadableFileRemoved::class)]
#[CoversClass(DownloadableFileUpdated::class)]
#[CoversClass(BundleCreated::class)]
#[CoversClass(BundleItemAdded::class)]
#[CoversClass(BundleItemRemoved::class)]
#[CoversClass(BundleRemoved::class)]
#[CoversClass(BundleUpdated::class)]
#[CoversClass(VariantAdded::class)]
final class CatalogExtendedEventsTest extends TestCase
{
    private ProductId $productId;
    private TenantId $tenantId;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->productId = ProductId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->now = new \DateTimeImmutable();
    }

    #[Test]
    public function downloadableFileAttachedEvent(): void
    {
        $event = new DownloadableFileAttached($this->productId, $this->tenantId, 'ebook.pdf', $this->now);

        self::assertSame($this->productId, $event->productId());
        self::assertSame($this->tenantId, $event->tenantId());
        self::assertSame('ebook.pdf', $event->filename());
        self::assertSame($this->now, $event->occurredOn());
        self::assertSame('catalog.downloadable_file.attached', $event->eventName());

        $array = $event->toArray();
        self::assertSame('ebook.pdf', $array['filename']);
    }

    #[Test]
    public function downloadableFileRemovedEvent(): void
    {
        $file = new DownloadableFile('ebook.pdf', 'https://cdn.example.com/ebook.pdf', 1048576);
        $event = new DownloadableFileRemoved($this->productId, $this->tenantId, $file, $this->now);

        self::assertSame($this->productId, $event->productId());
        self::assertSame($file, $event->file());
        self::assertSame('catalog.downloadable_file.removed', $event->eventName());

        $array = $event->toArray();
        self::assertSame('ebook.pdf', $array['filename']);
    }

    #[Test]
    public function downloadableFileUpdatedEvent(): void
    {
        $oldFile = new DownloadableFile('old.pdf', 'https://cdn.example.com/old.pdf', 500000);
        $newFile = new DownloadableFile('new.pdf', 'https://cdn.example.com/new.pdf', 750000);
        $event = new DownloadableFileUpdated($this->productId, $this->tenantId, $oldFile, $newFile, $this->now);

        self::assertSame($oldFile, $event->oldFile());
        self::assertSame($newFile, $event->newFile());
        self::assertSame('catalog.downloadable_file.updated', $event->eventName());

        $array = $event->toArray();
        self::assertSame('old.pdf', $array['oldFilename']);
        self::assertSame('new.pdf', $array['newFilename']);
    }

    #[Test]
    public function bundleCreatedEvent(): void
    {
        $bundledProductId = ProductId::generate();
        $item = BundleItem::create($bundledProductId, 2, Money::of('10.00', 'USD'));

        $event = new BundleCreated($this->productId, $this->tenantId, [$item], 10.0, $this->now);

        self::assertSame($this->productId, $event->productId());
        self::assertSame($this->tenantId, $event->tenantId());
        self::assertCount(1, $event->items());
        self::assertSame(10.0, $event->discountPercentage());
        self::assertSame('catalog.bundle.created', $event->eventName());

        $array = $event->toArray();
        self::assertSame(1, $array['itemCount']);
        self::assertSame(10.0, $array['discountPercentage']);
    }

    #[Test]
    public function bundleItemAddedEvent(): void
    {
        $bundledProductId = ProductId::generate();
        $item = BundleItem::create($bundledProductId, 3, Money::of('25.00', 'USD'));

        $event = new BundleItemAdded($this->productId, $this->tenantId, $item, $this->now);

        self::assertSame($this->productId, $event->productId());
        self::assertSame($item, $event->item());
        self::assertSame('catalog.bundle.item_added', $event->eventName());

        $array = $event->toArray();
        self::assertSame(3, $array['quantity']);
    }

    #[Test]
    public function bundleItemRemovedEvent(): void
    {
        $removedProductId = ProductId::generate();
        $event = new BundleItemRemoved($this->productId, $this->tenantId, $removedProductId, $this->now);

        self::assertSame($this->productId, $event->bundleProductId());
        self::assertSame($removedProductId, $event->removedProductId());
        self::assertSame('catalog.bundle.item_removed', $event->eventName());

        $array = $event->toArray();
        self::assertArrayHasKey('removedProductId', $array);
    }

    #[Test]
    public function bundleRemovedEvent(): void
    {
        $bundledProductId = ProductId::generate();
        $item = BundleItem::create($bundledProductId, 1, Money::of('15.00', 'USD'));
        $bundle = Bundle::create([$item], 5.0);

        $event = new BundleRemoved($this->productId, $this->tenantId, $bundle, $this->now);

        self::assertSame($this->productId, $event->productId());
        self::assertSame($bundle, $event->removedBundle());
        self::assertSame('catalog.bundle.removed', $event->eventName());

        $array = $event->toArray();
        self::assertSame(1, $array['removedItemCount']);
    }

    #[Test]
    public function bundleUpdatedEvent(): void
    {
        $p1 = ProductId::generate();
        $p2 = ProductId::generate();
        $oldBundle = Bundle::create([BundleItem::create($p1, 1, Money::of('10.00', 'USD'))], 5.0);
        $newBundle = Bundle::create([
            BundleItem::create($p1, 1, Money::of('10.00', 'USD')),
            BundleItem::create($p2, 2, Money::of('20.00', 'USD')),
        ], 15.0);

        $event = new BundleUpdated($this->productId, $this->tenantId, $oldBundle, $newBundle, $this->now);

        self::assertSame($oldBundle, $event->oldBundle());
        self::assertSame($newBundle, $event->newBundle());
        self::assertSame('catalog.bundle.updated', $event->eventName());

        $array = $event->toArray();
        self::assertSame(1, $array['oldItemCount']);
        self::assertSame(2, $array['newItemCount']);
    }

    #[Test]
    public function variantAddedEvent(): void
    {
        if (!class_exists(\App\Catalog\Domain\Model\ConfigurableProduct::class)) {
            self::markTestSkipped('ConfigurableProduct class not loadable');
        }

        $configProdId = \App\Catalog\Domain\Model\ConfigurableProductId::generate();
        $variantId = \App\Catalog\Domain\Model\VariantId::generate();
        $sku = VariantSKU::fromString('CLO-NIK-000123-red-xl');

        $event = new VariantAdded($configProdId, $this->productId, $variantId, $sku, ['color' => 'red', 'size' => 'xl']);

        self::assertSame($configProdId, $event->getConfigurableProductId());
        self::assertSame($variantId, $event->getVariantId());
        self::assertSame($sku, $event->getSku());
        self::assertSame(['color' => 'red', 'size' => 'xl'], $event->getOptionValueMap());
        self::assertSame('catalog.variant.added', $event->getEventName());

        $array = $event->toArray();
        self::assertSame('CLO-NIK-000123-red-xl', $array['sku']);
    }
}
