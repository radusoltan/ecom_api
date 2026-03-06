<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\EventSubscriber\FlashSaleActivatedSubscriber;
use App\Pricing\Domain\Event\FlashSaleActivated;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[CoversClass(FlashSaleActivatedSubscriber::class)]
final class FlashSaleActivatedSubscriberTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private FlashSaleActivatedSubscriber $subscriber;
    private TenantId $tenantId;
    private FlashSaleId $flashSaleId;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new FlashSaleActivatedSubscriber(logger: $this->logger);

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->flashSaleId = FlashSaleId::generate();
    }

    // -----------------------------------------------------------------------
    // getSubscribedEvents
    // -----------------------------------------------------------------------

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itSubscribesToFlashSaleActivatedEvent(): void
    {
        $events = FlashSaleActivatedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(FlashSaleActivated::class, $events);
        self::assertSame('onFlashSaleActivated', $events[FlashSaleActivated::class]);
    }

    #[Test]
    public function itSubscribesToExactlyOneEvent(): void
    {
        $events = FlashSaleActivatedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -----------------------------------------------------------------------
    // onFlashSaleActivated — success path
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoWhenFlashSaleActivated(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000010');
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Summer Flash Sale',
            productIds: [$productId],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Flash sale activated - ready for customer notifications',
                self::anything(),
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itIncludesFlashSaleIdInLogContext(): void
    {
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Weekend Deal',
            productIds: [],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('flash_sale_id', $context);
                    self::assertSame($this->flashSaleId->toString(), $context['flash_sale_id']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itIncludesTenantIdInLogContext(): void
    {
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Flash Sale',
            productIds: [],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('tenant_id', $context);
                    self::assertSame($this->tenantId->toString(), $context['tenant_id']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itIncludesFlashSaleNameInLogContext(): void
    {
        $saleName = 'Black Friday Flash';
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: $saleName,
            productIds: [],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context) use ($saleName): bool {
                    self::assertArrayHasKey('name', $context);
                    self::assertSame($saleName, $context['name']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itIncludesProductCountInLogContext(): void
    {
        $productIds = [
            ProductId::fromString('00000000-0000-4000-8000-000000000011'),
            ProductId::fromString('00000000-0000-4000-8000-000000000012'),
            ProductId::fromString('00000000-0000-4000-8000-000000000013'),
        ];

        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Three-Product Sale',
            productIds: $productIds,
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('product_count', $context);
                    self::assertSame(3, $context['product_count']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itLogsZeroProductCountForEmptyProductList(): void
    {
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Empty Flash Sale',
            productIds: [],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertSame(0, $context['product_count']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    // -----------------------------------------------------------------------
    // Error resilience
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenLoggerIsAvailable(): void
    {
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Sale',
            productIds: [],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger->method('info');

        // Must not throw
        $this->subscriber->onFlashSaleActivated($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itHandlesSingleProductFlashSale(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000099');
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Single Product Flash',
            productIds: [$productId],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Flash sale activated - ready for customer notifications',
                self::callback(function (array $context): bool {
                    self::assertSame(1, $context['product_count']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleActivated($event);
    }

    #[Test]
    public function itLogContextContainsAllRequiredKeys(): void
    {
        $event = new FlashSaleActivated(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Full Context Sale',
            productIds: [ProductId::fromString('00000000-0000-4000-8000-000000000011')],
            occurredAt: new \DateTimeImmutable(),
        );

        $capturedContext = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onFlashSaleActivated($event);

        self::assertArrayHasKey('flash_sale_id', $capturedContext);
        self::assertArrayHasKey('tenant_id', $capturedContext);
        self::assertArrayHasKey('name', $capturedContext);
        self::assertArrayHasKey('product_count', $capturedContext);
    }
}
