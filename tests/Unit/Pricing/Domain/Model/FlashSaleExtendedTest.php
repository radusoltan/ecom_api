<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Event\FlashSaleActivated;
use App\Pricing\Domain\Event\FlashSaleCancelled;
use App\Pricing\Domain\Event\FlashSaleEnded;
use App\Pricing\Domain\Event\FlashSaleScheduled;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Model\FlashSaleStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlashSale::class)]
final class FlashSaleExtendedTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Domain event assertions
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsFlashSaleScheduledEventOnCreate(): void
    {
        $flashSale = $this->makeFlashSale();
        $events = $flashSale->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(FlashSaleScheduled::class, $events[0]);
    }

    #[Test]
    public function itRecordsFlashSaleActivatedEventOnActivate(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->pullDomainEvents();

        $flashSale->activate();
        $events = $flashSale->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(FlashSaleActivated::class, $events[0]);
    }

    #[Test]
    public function itRecordsFlashSaleEndedEventOnEnd(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->activate();
        $flashSale->pullDomainEvents();

        $flashSale->end();
        $events = $flashSale->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(FlashSaleEnded::class, $events[0]);
    }

    #[Test]
    public function itRecordsFlashSaleCancelledEventOnCancel(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->pullDomainEvents();

        $flashSale->cancel();
        $events = $flashSale->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(FlashSaleCancelled::class, $events[0]);
    }

    // -----------------------------------------------------------------------
    // State transitions that must fail
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenEndingScheduledFlashSale(): void
    {
        $flashSale = $this->makeFlashSale();
        // Status is SCHEDULED, not ACTIVE

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot end flash sale in status: scheduled');

        $flashSale->end();
    }

    #[Test]
    public function itThrowsWhenEndingEndedFlashSale(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->activate();
        $flashSale->end();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot end flash sale in status: ended');

        $flashSale->end();
    }

    #[Test]
    public function itThrowsWhenEndingCancelledFlashSale(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot end flash sale in status: cancelled');

        $flashSale->end();
    }

    #[Test]
    public function itThrowsWhenCancellingEndedFlashSale(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->activate();
        $flashSale->end();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel flash sale in status: ended');

        $flashSale->cancel();
    }

    #[Test]
    public function itThrowsWhenCancellingAlreadyCancelledFlashSale(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel flash sale in status: cancelled');

        $flashSale->cancel();
    }

    #[Test]
    public function itThrowsWhenActivatingEndedFlashSale(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->activate();
        $flashSale->end();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot activate flash sale in status: ended');

        $flashSale->activate();
    }

    #[Test]
    public function itThrowsWhenActivatingCancelledFlashSale(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot activate flash sale in status: cancelled');

        $flashSale->activate();
    }

    // -----------------------------------------------------------------------
    // Status checks in detail
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsNotActiveAtGivenTimeWhenScheduled(): void
    {
        $start = new \DateTimeImmutable('+1 hour');
        $end = new \DateTimeImmutable('+5 hours');
        $flashSale = $this->makeFlashSale(startTime: $start, endTime: $end);

        $during = new \DateTimeImmutable('+3 hours');
        // Not activated, so isActiveAt is false even for a valid time window
        self::assertFalse($flashSale->isActiveAt($during));
    }

    #[Test]
    public function itIsNotActiveAtGivenTimeWhenEnded(): void
    {
        $start = new \DateTimeImmutable('+1 hour');
        $end = new \DateTimeImmutable('+5 hours');
        $flashSale = $this->makeFlashSale(startTime: $start, endTime: $end);
        $flashSale->activate();
        $flashSale->end();

        $during = new \DateTimeImmutable('+3 hours');
        self::assertFalse($flashSale->isActiveAt($during));
    }

    #[Test]
    public function itIsActiveAtExactStartTime(): void
    {
        $start = new \DateTimeImmutable('+1 hour');
        $end = new \DateTimeImmutable('+5 hours');
        $flashSale = $this->makeFlashSale(startTime: $start, endTime: $end);
        $flashSale->activate();

        self::assertTrue($flashSale->isActiveAt($start));
    }

    #[Test]
    public function itIsActiveAtExactEndTime(): void
    {
        $start = new \DateTimeImmutable('+1 hour');
        $end = new \DateTimeImmutable('+5 hours');
        $flashSale = $this->makeFlashSale(startTime: $start, endTime: $end);
        $flashSale->activate();

        self::assertTrue($flashSale->isActiveAt($end));
    }

    // -----------------------------------------------------------------------
    // Multiple products
    // -----------------------------------------------------------------------

    #[Test]
    public function itSupportsMultipleProducts(): void
    {
        $products = [ProductId::generate(), ProductId::generate(), ProductId::generate()];
        $flashSale = $this->makeFlashSale(productIds: $products);

        self::assertCount(3, $flashSale->productIds());
        foreach ($products as $productId) {
            self::assertTrue($flashSale->containsProduct($productId));
        }
    }

    #[Test]
    public function itDoesNotContainUnregisteredProduct(): void
    {
        $registeredProduct = ProductId::generate();
        $otherProduct = ProductId::generate();
        $flashSale = $this->makeFlashSale(productIds: [$registeredProduct]);

        self::assertFalse($flashSale->containsProduct($otherProduct));
    }

    // -----------------------------------------------------------------------
    // Duration calculation
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesDurationOfOneDayCorrectly(): void
    {
        $start = new \DateTimeImmutable('2026-03-01 10:00:00');
        $end = new \DateTimeImmutable('2026-03-02 10:00:00'); // exactly 24 hours
        $flashSale = $this->makeFlashSale(startTime: $start, endTime: $end);

        self::assertSame(24, $flashSale->getDurationInHours());
    }

    #[Test]
    public function itCalculatesMinimumAllowedDuration(): void
    {
        $start = new \DateTimeImmutable('2026-03-01 10:00:00');
        $end = new \DateTimeImmutable('2026-03-01 11:00:00'); // exactly 1 hour
        $flashSale = $this->makeFlashSale(startTime: $start, endTime: $end);

        self::assertSame(1, $flashSale->getDurationInHours());
    }

    #[Test]
    public function itCalculatesMaximumAllowedDuration(): void
    {
        $start = new \DateTimeImmutable('2026-03-01 00:00:00');
        $end = new \DateTimeImmutable('2026-03-08 00:00:00'); // exactly 168 hours = 7 days
        $flashSale = $this->makeFlashSale(startTime: $start, endTime: $end);

        self::assertSame(168, $flashSale->getDurationInHours());
    }

    // -----------------------------------------------------------------------
    // Name boundary validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsNameOfExactlyThreeCharacters(): void
    {
        $flashSale = $this->makeFlashSale(name: 'ABC');

        self::assertSame('ABC', $flashSale->name());
    }

    #[Test]
    public function itAcceptsNameOfExactlyOneHundredCharacters(): void
    {
        $name = str_repeat('X', 100);
        $flashSale = $this->makeFlashSale(name: $name);

        self::assertSame($name, $flashSale->name());
    }

    #[Test]
    public function itThrowsWhenNameIsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Flash sale name must be between 3 and 100 characters');

        $this->makeFlashSale(name: 'AB');
    }

    #[Test]
    public function itThrowsWhenNameIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Flash sale name must be between 3 and 100 characters');

        $this->makeFlashSale(name: str_repeat('Y', 101));
    }

    // -----------------------------------------------------------------------
    // Discount on FlashSale (Model\Discount, not ValueObject\Discount)
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesFixedDiscount(): void
    {
        $discount = Discount::fixed(\App\Shared\Domain\ValueObject\Money::of('20.00', 'USD'));
        $flashSale = FlashSale::create(
            id: FlashSaleId::generate(),
            tenantId: $this->tenantId,
            name: 'Fixed Discount Sale',
            productIds: [ProductId::generate()],
            discount: $discount,
            startTime: new \DateTimeImmutable('+1 hour'),
            endTime: new \DateTimeImmutable('+5 hours'),
        );

        self::assertTrue($flashSale->discount()->isFixed());
        self::assertFalse($flashSale->discount()->isPercentage());
    }

    // -----------------------------------------------------------------------
    // Reconstitution
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstitutesInCancelledStatus(): void
    {
        $now = new \DateTimeImmutable();
        $flashSale = FlashSale::reconstituteFromPersistence(
            id: FlashSaleId::generate(),
            tenantId: $this->tenantId,
            name: 'Cancelled Sale',
            productIds: [ProductId::generate()],
            discount: Discount::percentage(10.0),
            startTime: $now,
            endTime: $now->modify('+3 hours'),
            status: FlashSaleStatus::CANCELLED,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertTrue($flashSale->isCancelled());
        self::assertFalse($flashSale->isActive());
        self::assertFalse($flashSale->isScheduled());
        self::assertFalse($flashSale->isEnded());
    }

    #[Test]
    public function itReconstitutesInEndedStatus(): void
    {
        $now = new \DateTimeImmutable();
        $flashSale = FlashSale::reconstituteFromPersistence(
            id: FlashSaleId::generate(),
            tenantId: $this->tenantId,
            name: 'Ended Sale',
            productIds: [ProductId::generate()],
            discount: Discount::percentage(10.0),
            startTime: $now->modify('-5 hours'),
            endTime: $now->modify('-1 hour'),
            status: FlashSaleStatus::ENDED,
            createdAt: $now->modify('-6 hours'),
            updatedAt: $now->modify('-1 hour'),
        );

        self::assertTrue($flashSale->isEnded());
        self::assertEmpty($flashSale->pullDomainEvents());
    }

    #[Test]
    public function itUpdatesTimestampWhenActivated(): void
    {
        $flashSale = $this->makeFlashSale();
        $before = $flashSale->updatedAt();

        usleep(1000);
        $flashSale->activate();

        self::assertGreaterThanOrEqual($before, $flashSale->updatedAt());
    }

    #[Test]
    public function itUpdatesTimestampWhenEnded(): void
    {
        $flashSale = $this->makeFlashSale();
        $flashSale->activate();
        $before = $flashSale->updatedAt();

        usleep(1000);
        $flashSale->end();

        self::assertGreaterThanOrEqual($before, $flashSale->updatedAt());
    }

    #[Test]
    public function itUpdatesTimestampWhenCancelled(): void
    {
        $flashSale = $this->makeFlashSale();
        $before = $flashSale->updatedAt();

        usleep(1000);
        $flashSale->cancel();

        self::assertGreaterThanOrEqual($before, $flashSale->updatedAt());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeFlashSale(
        string $name = 'Test Flash Sale',
        ?array $productIds = null,
        ?\DateTimeImmutable $startTime = null,
        ?\DateTimeImmutable $endTime = null,
    ): FlashSale {
        return FlashSale::create(
            id: FlashSaleId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            productIds: $productIds ?? [ProductId::generate()],
            discount: Discount::percentage(20.0),
            startTime: $startTime ?? new \DateTimeImmutable('+1 hour'),
            endTime: $endTime ?? new \DateTimeImmutable('+5 hours'),
        );
    }
}
