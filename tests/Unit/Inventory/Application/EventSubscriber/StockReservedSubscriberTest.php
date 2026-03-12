<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\EventSubscriber\StockReservedSubscriber;
use App\Inventory\Domain\Event\StockReserved;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class StockReservedSubscriberTest extends TestCase
{
    private LoggerInterface $logger;
    private StockReservedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new StockReservedSubscriber($this->logger);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = StockReservedSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(StockReserved::class, $events);
        $this->assertEquals('onStockReserved', $events[StockReserved::class]);
    }

    public function testOnStockReservedLogsEvent(): void
    {
        $stockItemId = StockItemId::generate();
        $quantity = Quantity::fromInt(10);
        $reservationId = 'reservation-123';
        $occurredOn = new \DateTimeImmutable('2025-10-10 10:30:00');

        $event = new StockReserved(
            stockItemId: $stockItemId,
            tenantId: TenantId::generate(),
            productId: ProductId::generate(),
            quantity: $quantity,
            reservationId: $reservationId,
            occurredOn: $occurredOn
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Stock reserved',
                $this->callback(function ($context) use ($stockItemId, $quantity, $reservationId) {
                    $this->assertEquals($stockItemId->toString(), $context['stockItemId']);
                    $this->assertEquals($quantity->value(), $context['quantity']);
                    $this->assertEquals($reservationId, $context['reservationId']);
                    $this->assertIsString($context['occurredOn']);
                    $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $context['occurredOn']);

                    return true;
                })
            );

        $this->subscriber->onStockReserved($event);
    }
}
