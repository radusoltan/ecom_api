<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventSubscriber;

use App\Inventory\Domain\Event\StockAllocated;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles StockAllocated domain events for audit logging
 *
 * Business Rules:
 * - Log all stock allocations for audit trail
 * - Include order ID, quantity, and stock item details
 * - Track which order allocated the stock
 */
final readonly class StockAllocatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StockAllocated::class => 'onStockAllocated',
        ];
    }

    public function onStockAllocated(StockAllocated $event): void
    {
        $this->logger->info('Stock allocated', [
            'stockItemId' => $event->stockItemId->toString(),
            'quantity' => $event->quantity->value(),
            'orderId' => $event->orderId,
            'occurredOn' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
