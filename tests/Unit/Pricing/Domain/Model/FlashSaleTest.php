<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Model\FlashSaleStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlashSale::class)]
final class FlashSaleTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    private function createFlashSale(
        string $name = 'Summer Sale',
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

    #[Test]
    public function itCreatesFlashSaleWithValidData(): void
    {
        $flashSale = $this->createFlashSale();

        self::assertSame('Summer Sale', $flashSale->name());
        self::assertTrue($flashSale->isScheduled());
        self::assertFalse($flashSale->isActive());
        self::assertFalse($flashSale->isEnded());
        self::assertFalse($flashSale->isCancelled());
        self::assertCount(1, $flashSale->productIds());
    }

    #[Test]
    public function itThrowsOnNameTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createFlashSale(name: 'ab');
    }

    #[Test]
    public function itThrowsOnNameTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createFlashSale(name: str_repeat('x', 101));
    }

    #[Test]
    public function itThrowsOnEmptyProductIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one product');
        $this->createFlashSale(productIds: []);
    }

    #[Test]
    public function itThrowsWhenEndTimeBeforeStartTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('end time must be after start time');

        $this->createFlashSale(
            startTime: new \DateTimeImmutable('+5 hours'),
            endTime: new \DateTimeImmutable('+1 hour'),
        );
    }

    #[Test]
    public function itThrowsWhenDurationTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least');

        $this->createFlashSale(
            startTime: new \DateTimeImmutable('+1 hour'),
            endTime: new \DateTimeImmutable('+1 hour +30 minutes'),
        );
    }

    #[Test]
    public function itThrowsWhenDurationTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not exceed');

        $this->createFlashSale(
            startTime: new \DateTimeImmutable('+1 hour'),
            endTime: new \DateTimeImmutable('+200 hours'),
        );
    }

    #[Test]
    public function itActivatesScheduledFlashSale(): void
    {
        $flashSale = $this->createFlashSale();

        $flashSale->activate();

        self::assertTrue($flashSale->isActive());
        self::assertSame(FlashSaleStatus::ACTIVE, $flashSale->status());
    }

    #[Test]
    public function itEndsActiveFlashSale(): void
    {
        $flashSale = $this->createFlashSale();
        $flashSale->activate();

        $flashSale->end();

        self::assertTrue($flashSale->isEnded());
    }

    #[Test]
    public function itCancelsScheduledFlashSale(): void
    {
        $flashSale = $this->createFlashSale();

        $flashSale->cancel();

        self::assertTrue($flashSale->isCancelled());
    }

    #[Test]
    public function itThrowsWhenActivatingNonScheduled(): void
    {
        $flashSale = $this->createFlashSale();
        $flashSale->activate();

        $this->expectException(\DomainException::class);
        $flashSale->activate();
    }

    #[Test]
    public function itThrowsWhenEndingNonActive(): void
    {
        $flashSale = $this->createFlashSale();

        $this->expectException(\DomainException::class);
        $flashSale->end();
    }

    #[Test]
    public function itThrowsWhenCancellingActiveFlashSale(): void
    {
        $flashSale = $this->createFlashSale();
        $flashSale->activate();

        $this->expectException(\DomainException::class);
        $flashSale->cancel();
    }

    #[Test]
    public function itChecksIfActiveAtGivenTime(): void
    {
        $start = new \DateTimeImmutable('+1 hour');
        $end = new \DateTimeImmutable('+5 hours');
        $flashSale = $this->createFlashSale(startTime: $start, endTime: $end);
        $flashSale->activate();

        $during = new \DateTimeImmutable('+3 hours');
        $before = new \DateTimeImmutable('+30 minutes');
        $after = new \DateTimeImmutable('+6 hours');

        self::assertTrue($flashSale->isActiveAt($during));
        self::assertFalse($flashSale->isActiveAt($before));
        self::assertFalse($flashSale->isActiveAt($after));
    }

    #[Test]
    public function itChecksIfContainsProduct(): void
    {
        $productId = ProductId::generate();
        $otherProductId = ProductId::generate();
        $flashSale = $this->createFlashSale(productIds: [$productId]);

        self::assertTrue($flashSale->containsProduct($productId));
        self::assertFalse($flashSale->containsProduct($otherProductId));
    }

    #[Test]
    public function itCalculatesDurationInHours(): void
    {
        $start = new \DateTimeImmutable('2026-03-01 10:00:00');
        $end = new \DateTimeImmutable('2026-03-01 16:00:00');
        $flashSale = $this->createFlashSale(startTime: $start, endTime: $end);

        self::assertSame(6, $flashSale->getDurationInHours());
    }

    #[Test]
    public function itRecordsDomainEvents(): void
    {
        $flashSale = $this->createFlashSale();
        $events = $flashSale->pullDomainEvents();

        self::assertCount(1, $events); // FlashSaleScheduled
    }

    #[Test]
    public function itRecordsActivateEvent(): void
    {
        $flashSale = $this->createFlashSale();
        $flashSale->pullDomainEvents();

        $flashSale->activate();
        $events = $flashSale->pullDomainEvents();
        self::assertCount(1, $events); // FlashSaleActivated
    }

    #[Test]
    public function itReconstitutesFromPersistence(): void
    {
        $id = FlashSaleId::generate();
        $now = new \DateTimeImmutable();

        $flashSale = FlashSale::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            name: 'Flash Deal',
            productIds: [ProductId::generate()],
            discount: Discount::percentage(10.0),
            startTime: $now,
            endTime: $now->modify('+3 hours'),
            status: FlashSaleStatus::ACTIVE,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame('Flash Deal', $flashSale->name());
        self::assertTrue($flashSale->isActive());
    }

    #[Test]
    public function itExposesAllGetters(): void
    {
        $flashSale = $this->createFlashSale();

        self::assertInstanceOf(FlashSaleId::class, $flashSale->id());
        self::assertInstanceOf(TenantId::class, $flashSale->tenantId());
        self::assertInstanceOf(Discount::class, $flashSale->discount());
        self::assertInstanceOf(\DateTimeImmutable::class, $flashSale->startTime());
        self::assertInstanceOf(\DateTimeImmutable::class, $flashSale->endTime());
        self::assertInstanceOf(\DateTimeImmutable::class, $flashSale->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $flashSale->updatedAt());
    }
}
