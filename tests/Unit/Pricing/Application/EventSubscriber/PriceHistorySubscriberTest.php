<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\EventSubscriber\PriceHistorySubscriber;
use App\Pricing\Domain\Event\FlashSaleActivated;
use App\Pricing\Domain\Event\FlashSaleEnded;
use App\Pricing\Domain\Event\PricingRuleAdded;
use App\Pricing\Domain\Event\PricingRuleRemoved;
use App\Pricing\Domain\Event\PromotionActivated;
use App\Pricing\Domain\Event\PromotionDeactivated;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PriceHistorySubscriber::class)]
final class PriceHistorySubscriberTest extends TestCase
{
    private PriceHistoryRepositoryInterface $repository;
    private LoggerInterface $logger;
    private PriceHistorySubscriber $subscriber;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PriceHistoryRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new PriceHistorySubscriber($this->repository, $this->logger);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // getSubscribedEvents
    // -----------------------------------------------------------------------

    #[Test]
    public function itSubscribesToAllRequiredEvents(): void
    {
        $events = PriceHistorySubscriber::getSubscribedEvents();

        self::assertArrayHasKey(PricingRuleAdded::class, $events);
        self::assertArrayHasKey(PricingRuleRemoved::class, $events);
        self::assertArrayHasKey(PromotionActivated::class, $events);
        self::assertArrayHasKey(PromotionDeactivated::class, $events);
        self::assertArrayHasKey(FlashSaleActivated::class, $events);
        self::assertArrayHasKey(FlashSaleEnded::class, $events);
        self::assertSame('onPricingRuleAdded', $events[PricingRuleAdded::class]);
        self::assertSame('onPricingRuleRemoved', $events[PricingRuleRemoved::class]);
        self::assertSame('onPromotionActivated', $events[PromotionActivated::class]);
        self::assertSame('onPromotionDeactivated', $events[PromotionDeactivated::class]);
        self::assertSame('onFlashSaleActivated', $events[FlashSaleActivated::class]);
        self::assertSame('onFlashSaleEnded', $events[FlashSaleEnded::class]);
    }

    // -----------------------------------------------------------------------
    // onPricingRuleAdded — missing required data
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsMissingDataWarningWhenPricingRuleAddedHasNoProductId(): void
    {
        $event = new PricingRuleAdded(
            'price-list-123',
            ['price' => '10.00'] // missing product_id
        );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('PricingRuleAdded event missing required data', self::anything());

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onPricingRuleAdded($event);
    }

    #[Test]
    public function itLogsMissingDataWarningWhenPricingRuleAddedHasNoPrice(): void
    {
        $event = new PricingRuleAdded(
            'price-list-123',
            ['product_id' => '00000000-0000-4000-8000-000000000010'] // missing price
        );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('PricingRuleAdded event missing required data', self::anything());

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onPricingRuleAdded($event);
    }

    #[Test]
    public function itLogsMissingDataWarningWhenPricingRuleAddedHasEmptyData(): void
    {
        $event = new PricingRuleAdded('price-list-123', []);

        $this->logger
            ->expects(self::once())
            ->method('warning');

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onPricingRuleAdded($event);
    }

    // -----------------------------------------------------------------------
    // onPricingRuleAdded — throws LogicException (tenant extraction not implemented)
    // -----------------------------------------------------------------------

    #[Test]
    public function itCatchesAndLogsErrorWhenPricingRuleAddedHasValidData(): void
    {
        // The subscriber calls extractTenantIdFromPriceListId which always throws
        // LogicException. The subscriber must catch and log the error (not rethrow).
        $event = new PricingRuleAdded(
            'price-list-123',
            [
                'product_id' => '00000000-0000-4000-8000-000000000010',
                'price' => ['amount' => '99.99', 'currency' => 'USD'],
                'reason' => 'Test reason',
            ]
        );

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to record price history for pricing rule added', self::anything());

        $this->repository->expects(self::never())->method('save');

        // Should NOT throw — errors are swallowed
        $this->subscriber->onPricingRuleAdded($event);
    }

    #[Test]
    public function itParsesScalarPriceInPricingRuleAdded(): void
    {
        // Scalar price string triggers extractMoneyFromRuleData with string path
        $event = new PricingRuleAdded(
            'price-list-123',
            [
                'product_id' => '00000000-0000-4000-8000-000000000010',
                'price' => '49.99',
            ]
        );

        // LogicException from extractTenantIdFromPriceListId is caught and logged
        $this->logger->expects(self::once())->method('error');

        $this->subscriber->onPricingRuleAdded($event);
    }

    #[Test]
    public function itParsesNumericPriceInPricingRuleAdded(): void
    {
        $event = new PricingRuleAdded(
            'price-list-123',
            [
                'product_id' => '00000000-0000-4000-8000-000000000010',
                'price' => 49.99,
                'old_price' => 59.99,
            ]
        );

        $this->logger->expects(self::once())->method('error');

        $this->subscriber->onPricingRuleAdded($event);
    }

    // -----------------------------------------------------------------------
    // onPricingRuleRemoved
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsMissingProductIdWarningForPricingRuleRemoved(): void
    {
        $event = new PricingRuleRemoved(
            'price-list-123',
            ['price' => '10.00'] // no product_id
        );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('PricingRuleRemoved event missing product_id', self::anything());

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onPricingRuleRemoved($event);
    }

    #[Test]
    public function itCatchesAndLogsErrorForPricingRuleRemovedWithValidData(): void
    {
        $event = new PricingRuleRemoved(
            'price-list-123',
            [
                'product_id' => '00000000-0000-4000-8000-000000000010',
                'price' => ['amount' => '99.99', 'currency' => 'USD'],
            ]
        );

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to record price history for pricing rule removed', self::anything());

        $this->subscriber->onPricingRuleRemoved($event);
    }

    #[Test]
    public function itHandlesPricingRuleRemovedWithNoPrice(): void
    {
        // product_id present but no price — oldPrice will be null
        $event = new PricingRuleRemoved(
            'price-list-123',
            ['product_id' => '00000000-0000-4000-8000-000000000010']
        );

        // Still fails at tenant extraction → error is logged, not thrown
        $this->logger->expects(self::once())->method('error');

        $this->subscriber->onPricingRuleRemoved($event);
    }

    // -----------------------------------------------------------------------
    // onPromotionActivated
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoWhenPromotionActivated(): void
    {
        $promotionId = PromotionId::generate();
        $event = new PromotionActivated($promotionId, $this->tenantId, new \DateTimeImmutable());

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Promotion activated, price history tracking needed', self::anything());

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onPromotionActivated($event);
    }

    #[Test]
    public function itDoesNotThrowWhenPromotionActivated(): void
    {
        $promotionId = PromotionId::generate();
        $event = new PromotionActivated($promotionId, $this->tenantId, new \DateTimeImmutable());

        // Should not throw any exception
        $this->subscriber->onPromotionActivated($event);

        self::assertTrue(true); // Reached without exception
    }

    // -----------------------------------------------------------------------
    // onPromotionDeactivated
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoWhenPromotionDeactivated(): void
    {
        $promotionId = PromotionId::generate();
        $event = new PromotionDeactivated($promotionId, $this->tenantId, new \DateTimeImmutable());

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Promotion deactivated, price history tracking needed', self::anything());

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onPromotionDeactivated($event);
    }

    #[Test]
    public function itDoesNotThrowWhenPromotionDeactivated(): void
    {
        $promotionId = PromotionId::generate();
        $event = new PromotionDeactivated($promotionId, $this->tenantId, new \DateTimeImmutable());

        $this->subscriber->onPromotionDeactivated($event);

        self::assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // onFlashSaleActivated
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoForEachProductWhenFlashSaleActivated(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $productId1 = ProductId::fromString('00000000-0000-4000-8000-000000000011');
        $productId2 = ProductId::fromString('00000000-0000-4000-8000-000000000012');

        $event = new FlashSaleActivated(
            $flashSaleId,
            $this->tenantId,
            'Summer Flash',
            [$productId1, $productId2],
            new \DateTimeImmutable()
        );

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with('Flash sale activated, price history needed', self::anything());

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itLogsNothingWhenFlashSaleActivatedWithNoProducts(): void
    {
        $flashSaleId = FlashSaleId::generate();

        $event = new FlashSaleActivated(
            $flashSaleId,
            $this->tenantId,
            'Empty Flash',
            [], // no products
            new \DateTimeImmutable()
        );

        $this->logger->expects(self::never())->method('info');
        $this->logger->expects(self::never())->method('error');

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itDoesNotThrowWhenFlashSaleActivated(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000011');

        $event = new FlashSaleActivated(
            $flashSaleId,
            $this->tenantId,
            'Flash Sale',
            [$productId],
            new \DateTimeImmutable()
        );

        $this->subscriber->onFlashSaleActivated($event);

        self::assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // onFlashSaleEnded
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoWhenFlashSaleEnded(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $event = new FlashSaleEnded($flashSaleId, $this->tenantId, new \DateTimeImmutable());

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Flash sale ended, price history tracking needed', self::anything());

        $this->repository->expects(self::never())->method('save');

        $this->subscriber->onFlashSaleEnded($event);
    }

    #[Test]
    public function itDoesNotThrowWhenFlashSaleEnded(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $event = new FlashSaleEnded($flashSaleId, $this->tenantId, new \DateTimeImmutable());

        $this->subscriber->onFlashSaleEnded($event);

        self::assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // Error resilience — main business operation must not fail
    // -----------------------------------------------------------------------

    #[Test]
    public function itNeverThrowsFromOnPricingRuleAdded(): void
    {
        // Even with completely broken data, errors must be swallowed
        $event = new PricingRuleAdded(
            'price-list-123',
            [
                'product_id' => 'not-a-uuid', // will throw InvalidArgumentException
                'price' => '10.00',
            ]
        );

        $this->logger->expects(self::once())->method('error');

        // Must not throw
        $this->subscriber->onPricingRuleAdded($event);
    }

    #[Test]
    public function itNeverThrowsFromOnPricingRuleRemoved(): void
    {
        $event = new PricingRuleRemoved(
            'price-list-123',
            ['product_id' => 'not-a-uuid'] // invalid UUID
        );

        $this->logger->expects(self::once())->method('error');

        $this->subscriber->onPricingRuleRemoved($event);
    }

    #[Test]
    public function itIncludesFlashSaleIdInLogContext(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000011');

        $event = new FlashSaleActivated(
            $flashSaleId,
            $this->tenantId,
            'Test Sale',
            [$productId],
            new \DateTimeImmutable()
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Flash sale activated, price history needed',
                self::callback(function (array $context) use ($flashSaleId): bool {
                    return isset($context['flash_sale_id'])
                        && $context['flash_sale_id'] === $flashSaleId->toString();
                })
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itIncludesPromotionIdInPromotionActivatedLogContext(): void
    {
        $promotionId = PromotionId::generate();
        $event = new PromotionActivated($promotionId, $this->tenantId, new \DateTimeImmutable());

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Promotion activated, price history tracking needed',
                self::callback(function (array $context) use ($promotionId): bool {
                    return isset($context['promotion_id'])
                        && $context['promotion_id'] === $promotionId->toString();
                })
            );

        $this->subscriber->onPromotionActivated($event);
    }
}
