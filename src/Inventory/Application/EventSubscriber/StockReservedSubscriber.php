<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventSubscriber;

use App\Inventory\Domain\Event\StockReserved;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles StockReserved domain events for audit logging.
 *
 * Business Rules:
 * - Log all stock reservations for audit trail
 * - Include reservation ID, quantity, and stock item details
 * - Track which customer/cart initiated the reservation
 */
final readonly class StockReservedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StockReserved::class => 'onStockReserved',
        ];
    }

    public function onStockReserved(StockReserved $event): void
    {
        $this->logger->info('Stock reserved', [
            'stockItemId' => $event->stockItemId->toString(),
            'quantity' => $event->quantity->value(),
            'reservationId' => $event->reservationId,
            'occurredOn' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
