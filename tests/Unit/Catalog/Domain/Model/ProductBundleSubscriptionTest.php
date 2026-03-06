<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Event\BundleCreated;
use App\Catalog\Domain\Event\BundleItemAdded;
use App\Catalog\Domain\Event\BundleItemRemoved;
use App\Catalog\Domain\Event\BundleRemoved;
use App\Catalog\Domain\Event\BundleUpdated;
use App\Catalog\Domain\Event\DownloadableFileAttached;
use App\Catalog\Domain\Event\DownloadableFileRemoved;
use App\Catalog\Domain\Event\DownloadableFileUpdated;
use App\Catalog\Domain\Event\SubscriptionConfigured;
use App\Catalog\Domain\Event\SubscriptionRemoved;
use App\Catalog\Domain\Event\SubscriptionUpdated;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Product::class)]
final class ProductBundleSubscriptionTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    private function makeProduct(ProductType $type, int $price = 5000): Product
    {
        static $counter = 1000;
        ++$counter;
        $padded = str_pad((string) $counter, 6, '0', STR_PAD_LEFT);

        return Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString("TST-{$padded}"),
            name: ProductName::fromString('Test Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars($price, 'USD'),
            categoryId: null,
            stock: Stock::create(10),
            type: $type,
        );
    }

    private function makeBundleItem(int $price = 1000): BundleItem
    {
        return BundleItem::create(
            productId: ProductId::generate(),
            quantity: 1,
            price: Money::fromScalars($price, 'USD'),
        );
    }

    // -------------------------------------------------------
    // Bundle operations
    // -------------------------------------------------------

    #[Test]
    public function itCreatesBundleForBundleTypeProduct(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $items = [$this->makeBundleItem(1000), $this->makeBundleItem(2000)];

        $product->createBundle($items, 10.0);
        $product->popEvents();

        self::assertTrue($product->hasBundle());
        self::assertNotNull($product->bundle());
        self::assertSame(10.0, $product->bundle()->discountPercentage());
    }

    #[Test]
    public function itThrowsWhenCreatingBundleOnNonBundleProduct(): void
    {
        $product = $this->makeProduct(ProductType::simple());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot create bundle for product type "simple"');

        $product->createBundle([$this->makeBundleItem()]);
    }

    #[Test]
    public function itThrowsWhenCreatingBundleWhenBundleAlreadyExists(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem()]);
        $product->popEvents();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product already has a bundle configuration');

        $product->createBundle([$this->makeBundleItem()]);
    }

    #[Test]
    public function itRecordsBundleCreatedEvent(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $items = [$this->makeBundleItem(1500)];
        $product->popEvents(); // clear create event

        $product->createBundle($items, 5.0);

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BundleCreated::class, $events[0]);
        self::assertSame(5.0, $events[0]->discountPercentage());
    }

    #[Test]
    public function itUpdatesBundleConfiguration(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem(1000)], 0.0);
        $product->popEvents();

        $newItems = [$this->makeBundleItem(2000), $this->makeBundleItem(3000)];
        $product->updateBundle($newItems, 20.0);

        self::assertSame(20.0, $product->bundle()->discountPercentage());
    }

    #[Test]
    public function itRecordsBundleUpdatedEvent(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem(1000)], 0.0);
        $product->popEvents();

        $product->updateBundle([$this->makeBundleItem(2000)], 15.0);

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BundleUpdated::class, $events[0]);
    }

    #[Test]
    public function itThrowsUpdateBundleWhenNoBundleExists(): void
    {
        $product = $this->makeProduct(ProductType::bundle());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not have a bundle configuration');

        $product->updateBundle([$this->makeBundleItem()]);
    }

    #[Test]
    public function itAddsItemToBundle(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem(1000)]);
        $product->popEvents();

        $newItem = $this->makeBundleItem(500);
        $product->addBundleItem($newItem);

        self::assertCount(2, $product->bundle()->items());
    }

    #[Test]
    public function itRecordsBundleItemAddedEvent(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem(1000)]);
        $product->popEvents();

        $newItem = $this->makeBundleItem(500);
        $product->addBundleItem($newItem);

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BundleItemAdded::class, $events[0]);
    }

    #[Test]
    public function itRemovesBundleItem(): void
    {
        $itemProductId = ProductId::generate();
        $item = BundleItem::create($itemProductId, 1, Money::fromScalars(1000, 'USD'));
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$item, $this->makeBundleItem(2000)]);
        $product->popEvents();

        $product->removeBundleItem($itemProductId);

        self::assertCount(1, $product->bundle()->items());
    }

    #[Test]
    public function itRecordsBundleItemRemovedEvent(): void
    {
        $itemProductId = ProductId::generate();
        $item = BundleItem::create($itemProductId, 1, Money::fromScalars(1000, 'USD'));
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$item, $this->makeBundleItem(2000)]);
        $product->popEvents();

        $product->removeBundleItem($itemProductId);

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BundleItemRemoved::class, $events[0]);
        self::assertTrue($events[0]->removedProductId()->equals($itemProductId));
    }

    #[Test]
    public function itThrowsWhenRemovingNonExistentBundleItem(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem(1000), $this->makeBundleItem(2000)]);
        $product->popEvents();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        $product->removeBundleItem(ProductId::generate()); // random ID not in bundle
    }

    #[Test]
    public function itRemovesBundleConfiguration(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem(1000)]);
        $product->popEvents();

        $product->removeBundle();

        self::assertFalse($product->hasBundle());
        self::assertNull($product->bundle());
    }

    #[Test]
    public function itRecordsBundleRemovedEvent(): void
    {
        $product = $this->makeProduct(ProductType::bundle());
        $product->createBundle([$this->makeBundleItem(1000)]);
        $product->popEvents();

        $product->removeBundle();

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BundleRemoved::class, $events[0]);
    }

    #[Test]
    public function itThrowsRemoveBundleWhenNoBundleExists(): void
    {
        $product = $this->makeProduct(ProductType::bundle());

        $this->expectException(\DomainException::class);

        $product->removeBundle();
    }

    // -------------------------------------------------------
    // Subscription operations
    // -------------------------------------------------------

    #[Test]
    public function itConfiguresSubscription(): void
    {
        $product = $this->makeProduct(ProductType::subscription());
        $setupFee = Money::fromScalars(0, 'USD');

        $product->configureSubscription(
            interval: SubscriptionInterval::MONTHLY,
            billingCycles: 12,
            setupFee: $setupFee,
        );

        self::assertTrue($product->hasSubscription());
        self::assertNotNull($product->subscription());
    }

    #[Test]
    public function itThrowsWhenConfiguringSubscriptionOnNonSubscriptionProduct(): void
    {
        $product = $this->makeProduct(ProductType::simple());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot configure subscription for product type "simple"');

        $product->configureSubscription(
            interval: SubscriptionInterval::MONTHLY,
            billingCycles: 12,
            setupFee: Money::fromScalars(0, 'USD'),
        );
    }

    #[Test]
    public function itThrowsWhenSubscriptionAlreadyConfigured(): void
    {
        $product = $this->makeProduct(ProductType::subscription());
        $product->configureSubscription(SubscriptionInterval::MONTHLY, 12, Money::fromScalars(0, 'USD'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product already has a subscription configuration');

        $product->configureSubscription(SubscriptionInterval::MONTHLY, 6, Money::fromScalars(0, 'USD'));
    }

    #[Test]
    public function itRecordsSubscriptionConfiguredEvent(): void
    {
        $product = $this->makeProduct(ProductType::subscription());
        $product->popEvents();

        $product->configureSubscription(SubscriptionInterval::MONTHLY, 12, Money::fromScalars(0, 'USD'));

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SubscriptionConfigured::class, $events[0]);
        self::assertSame(12, $events[0]->billingCycles());
    }

    #[Test]
    public function itConfiguresSubscriptionWithTrial(): void
    {
        $product = $this->makeProduct(ProductType::subscription());
        $trialEnd = new \DateTimeImmutable('+30 days');

        $product->configureSubscription(
            interval: SubscriptionInterval::MONTHLY,
            billingCycles: 0,
            setupFee: Money::fromScalars(0, 'USD'),
            trialPeriodEnd: $trialEnd,
        );

        self::assertTrue($product->hasSubscription());
        self::assertNotNull($product->subscription()->trialPeriodEnd());
    }

    #[Test]
    public function itUpdatesSubscription(): void
    {
        $product = $this->makeProduct(ProductType::subscription());
        $product->configureSubscription(SubscriptionInterval::MONTHLY, 12, Money::fromScalars(0, 'USD'));
        $product->popEvents();

        $product->updateSubscription(SubscriptionInterval::YEARLY, 0, Money::fromScalars(500, 'USD'));

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SubscriptionUpdated::class, $events[0]);
    }

    #[Test]
    public function itThrowsUpdateSubscriptionWhenNoneExists(): void
    {
        $product = $this->makeProduct(ProductType::subscription());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not have a subscription configuration');

        $product->updateSubscription(SubscriptionInterval::MONTHLY, 6, Money::fromScalars(0, 'USD'));
    }

    #[Test]
    public function itRemovesSubscription(): void
    {
        $product = $this->makeProduct(ProductType::subscription());
        $product->configureSubscription(SubscriptionInterval::MONTHLY, 12, Money::fromScalars(0, 'USD'));
        $product->popEvents();

        $product->removeSubscription();

        self::assertFalse($product->hasSubscription());
        self::assertNull($product->subscription());
    }

    #[Test]
    public function itRecordsSubscriptionRemovedEvent(): void
    {
        $product = $this->makeProduct(ProductType::subscription());
        $product->configureSubscription(SubscriptionInterval::MONTHLY, 12, Money::fromScalars(0, 'USD'));
        $product->popEvents();

        $product->removeSubscription();

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SubscriptionRemoved::class, $events[0]);
    }

    #[Test]
    public function itThrowsRemoveSubscriptionWhenNoneExists(): void
    {
        $product = $this->makeProduct(ProductType::subscription());

        $this->expectException(\DomainException::class);

        $product->removeSubscription();
    }

    // -------------------------------------------------------
    // Downloadable file operations
    // -------------------------------------------------------

    #[Test]
    public function itAttachesDownloadableFile(): void
    {
        $product = $this->makeProduct(ProductType::virtual());

        $product->attachDownloadableFile(
            filename: 'ebook.pdf',
            fileUrl: 'https://cdn.example.com/ebook.pdf',
            fileSizeBytes: 1024 * 1024,
            downloadLimit: 5,
        );

        self::assertTrue($product->hasDownloadableFile());
        self::assertNotNull($product->downloadableFile());
    }

    #[Test]
    public function itThrowsWhenAttachingFileToNonVirtualProduct(): void
    {
        $product = $this->makeProduct(ProductType::simple());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot attach downloadable file to product type "simple"');

        $product->attachDownloadableFile('ebook.pdf', 'https://cdn.com/ebook.pdf', 1024);
    }

    #[Test]
    public function itThrowsWhenFileAlreadyAttached(): void
    {
        $product = $this->makeProduct(ProductType::virtual());
        $product->attachDownloadableFile('ebook.pdf', 'https://cdn.com/ebook.pdf', 1024);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product already has a downloadable file');

        $product->attachDownloadableFile('other.pdf', 'https://cdn.com/other.pdf', 2048);
    }

    #[Test]
    public function itRecordsDownloadableFileAttachedEvent(): void
    {
        $product = $this->makeProduct(ProductType::virtual());
        $product->popEvents();

        $product->attachDownloadableFile('guide.pdf', 'https://cdn.com/guide.pdf', 512000);

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(DownloadableFileAttached::class, $events[0]);
        self::assertSame('guide.pdf', $events[0]->filename());
    }

    #[Test]
    public function itAttachesDownloadableFileWithExpiry(): void
    {
        $product = $this->makeProduct(ProductType::virtual());
        $expiresAt = new \DateTimeImmutable('+1 year');

        $product->attachDownloadableFile(
            filename: 'course.mp4',
            fileUrl: 'https://cdn.com/course.mp4',
            fileSizeBytes: 500 * 1024 * 1024,
            downloadLimit: 3,
            expiresAt: $expiresAt,
        );

        self::assertTrue($product->hasDownloadableFile());
        self::assertNotNull($product->downloadableFile()->expiresAt());
    }

    #[Test]
    public function itUpdatesDownloadableFile(): void
    {
        $product = $this->makeProduct(ProductType::virtual());
        $product->attachDownloadableFile('old.pdf', 'https://cdn.com/old.pdf', 1024);
        $product->popEvents();

        $product->updateDownloadableFile('new.pdf', 'https://cdn.com/new.pdf', 2048, 10);

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(DownloadableFileUpdated::class, $events[0]);
        self::assertSame('old.pdf', $events[0]->oldFile()->filename());
        self::assertSame('new.pdf', $events[0]->newFile()->filename());
    }

    #[Test]
    public function itThrowsUpdateDownloadableFileWhenNoneExists(): void
    {
        $product = $this->makeProduct(ProductType::virtual());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not have a downloadable file');

        $product->updateDownloadableFile('new.pdf', 'https://cdn.com/new.pdf', 1024);
    }

    #[Test]
    public function itRemovesDownloadableFile(): void
    {
        $product = $this->makeProduct(ProductType::virtual());
        $product->attachDownloadableFile('file.pdf', 'https://cdn.com/file.pdf', 1024);
        $product->popEvents();

        $product->removeDownloadableFile();

        self::assertFalse($product->hasDownloadableFile());
        self::assertNull($product->downloadableFile());
    }

    #[Test]
    public function itRecordsDownloadableFileRemovedEvent(): void
    {
        $product = $this->makeProduct(ProductType::virtual());
        $product->attachDownloadableFile('manual.pdf', 'https://cdn.com/manual.pdf', 1024);
        $product->popEvents();

        $product->removeDownloadableFile();

        $events = $product->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(DownloadableFileRemoved::class, $events[0]);
    }

    #[Test]
    public function itThrowsRemoveDownloadableFileWhenNoneExists(): void
    {
        $product = $this->makeProduct(ProductType::virtual());

        $this->expectException(\DomainException::class);

        $product->removeDownloadableFile();
    }

    #[Test]
    public function itThrowsUpdateDownloadableFileForNonVirtual(): void
    {
        $product = $this->makeProduct(ProductType::simple());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot perform downloadable file operation on product type "simple"');

        $product->updateDownloadableFile('file.pdf', 'https://cdn.com/file.pdf', 1024);
    }

    #[Test]
    public function itThrowsRemoveDownloadableFileForNonVirtual(): void
    {
        $product = $this->makeProduct(ProductType::simple());

        $this->expectException(\DomainException::class);

        $product->removeDownloadableFile();
    }

    // -------------------------------------------------------
    // reconstituteFromPersistence with optional fields
    // -------------------------------------------------------

    #[Test]
    public function itReconstitutesWithBundleAndSubscription(): void
    {
        $id = ProductId::generate();
        $bundleItem = $this->makeBundleItem(1000);
        $bundle = \App\Catalog\Domain\ValueObject\Bundle::create([$bundleItem], 0.0);
        $subscription = \App\Catalog\Domain\ValueObject\Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD'),
        );

        $product = Product::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            sku: SKU::fromString('TST-999999'),
            name: ProductName::fromString('Bundle+Sub Product'),
            description: null,
            shortDescription: null,
            slug: \App\Catalog\Domain\Model\Slug::fromString('bundle-sub-product'),
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10),
            images: [],
            status: \App\Catalog\Domain\ValueObject\ProductStatus::active(),
            type: ProductType::bundle(),
            isFeatured: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            bundle: $bundle,
            subscription: $subscription,
        );

        self::assertTrue($product->hasBundle());
        self::assertTrue($product->hasSubscription());
        // No events recorded on reconstitution
        self::assertCount(0, $product->popEvents());
    }
}
