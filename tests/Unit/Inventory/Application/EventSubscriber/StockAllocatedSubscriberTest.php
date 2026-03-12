<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\EventSubscriber\StockAllocatedSubscriber;
use App\Inventory\Domain\Event\StockAllocated;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(StockAllocatedSubscriber::class)]
final class StockAllocatedSubscriberTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private StockAllocatedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new StockAllocatedSubscriber(
            logger: $this->logger,
        );
    }

    #[Test]
    public function itSubscribesToStockAllocatedEvent(): void
    {
        $events = StockAllocatedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(StockAllocated::class, $events);
        self::assertSame('onStockAllocated', $events[StockAllocated::class]);
    }

    #[Test]
    public function itReturnsExactlyOneSubscribedEvent(): void
    {
        $events = StockAllocatedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    #[Test]
    public function itLogsInfoWhenStockIsAllocated(): void
    {
        $stockItemId = StockItemId::generate();
        $quantity = Quantity::fromInt(5);
        $orderId = 'order-abc-123';
        $occurredOn = new \DateTimeImmutable('2026-01-15 10:00:00');

        $event = $this->createEvent(
            stockItemId: $stockItemId,
            quantity: $quantity,
            orderId: $orderId,
            occurredOn: $occurredOn,
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Stock allocated',
                self::callback(function (array $context) use ($stockItemId, $quantity, $orderId): bool {
                    return $context['stockItemId'] === $stockItemId->toString()
                        && $context['quantity'] === $quantity->value()
                        && $context['orderId'] === $orderId
                        && isset($context['occurredOn']);
                }),
            );

        $this->subscriber->onStockAllocated($event);
    }

    #[Test]
    public function itLogsStockItemIdInContext(): void
    {
        $stockItemId = StockItemId::generate();

        $event = $this->createEvent(
            stockItemId: $stockItemId,
            quantity: Quantity::fromInt(10),
            orderId: 'order-001',
        );

        $capturedContext = null;
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onStockAllocated($event);

        self::assertSame($stockItemId->toString(), $capturedContext['stockItemId']);
    }

    #[Test]
    public function itLogsQuantityInContext(): void
    {
        $event = $this->createEvent(
            quantity: Quantity::fromInt(7),
            orderId: 'order-002',
        );

        $capturedContext = null;
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onStockAllocated($event);

        self::assertSame(7, $capturedContext['quantity']);
    }

    #[Test]
    public function itLogsOrderIdInContext(): void
    {
        $orderId = '01JKXYZ-SPECIFIC-ORDER-ID';

        $event = $this->createEvent(
            quantity: Quantity::fromInt(3),
            orderId: $orderId,
        );

        $capturedContext = null;
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onStockAllocated($event);

        self::assertSame($orderId, $capturedContext['orderId']);
    }

    #[Test]
    public function itLogsOccurredOnTimestampInContext(): void
    {
        $event = $this->createEvent(
            quantity: Quantity::fromInt(2),
            orderId: 'order-003',
        );

        $capturedContext = null;
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onStockAllocated($event);

        self::assertArrayHasKey('occurredOn', $capturedContext);
        self::assertIsString($capturedContext['occurredOn']);
    }

    #[Test]
    public function itHandlesLargeQuantityAllocation(): void
    {
        $event = $this->createEvent(
            quantity: Quantity::fromInt(9999),
            orderId: 'bulk-order-001',
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Stock allocated',
                self::callback(fn (array $ctx): bool => 9999 === $ctx['quantity']),
            );

        $this->subscriber->onStockAllocated($event);
    }

    private function createEvent(
        ?StockItemId $stockItemId = null,
        ?Quantity $quantity = null,
        string $orderId = 'order-default',
        ?\DateTimeImmutable $occurredOn = null,
    ): StockAllocated {
        return new StockAllocated(
            stockItemId: $stockItemId ?? StockItemId::generate(),
            tenantId: TenantId::generate(),
            productId: ProductId::generate(),
            quantity: $quantity ?? Quantity::fromInt(1),
            orderId: $orderId,
            occurredOn: $occurredOn ?? new \DateTimeImmutable(),
        );
    }
}
