<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventSubscriber;

use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use App\Order\Domain\Event\OrderCancelled;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Handles OrderCancelled domain events by releasing all stock reservations for the order.
 *
 * Business Rules:
 * - Release all stock reservations when order is cancelled
 * - Log each reservation release for audit trail
 * - Emit StockReleased events for each released reservation
 * - Gracefully handle errors - log failures but don't block cancellation
 * - Include cancellation reason in audit log
 */
final readonly class OrderCancelledStockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private StockReservationRepositoryInterface $reservationRepository,
        private StockItemRepositoryInterface $stockItemRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderCancelled::class => 'onOrderCancelled',
        ];
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        try {
            $orderId = $event->orderId->toString();
            $reason = sprintf(
                'Order cancelled%s',
                $event->reason ? ': '.$event->reason : ''
            );

            $this->logger->info('Processing stock release for cancelled order', [
                'orderId' => $orderId,
                'tenantId' => $event->tenantId->toString(),
                'reason' => $event->reason,
            ]);

            $reservations = $this->reservationRepository->findByOrderId($orderId);

            if (empty($reservations)) {
                $this->logger->info('No stock reservations found for cancelled order', [
                    'orderId' => $orderId,
                ]);

                return;
            }

            $releasedCount = 0;
            $failedCount = 0;

            foreach ($reservations as $reservation) {
                try {
                    if ($reservation->isReleased()) {
                        $this->logger->debug('Reservation already released, skipping', [
                            'orderId' => $orderId,
                            'reservationId' => $reservation->reservationId(),
                        ]);
                        continue;
                    }

                    $reservation->release();
                    $this->reservationRepository->save($reservation);

                    $stockItem = $this->stockItemRepository->findById($reservation->stockItemId());
                    if (null === $stockItem) {
                        ++$failedCount;

                        $this->logger->warning('Stock item not found for cancelled order reservation', [
                            'orderId' => $orderId,
                            'reservationId' => $reservation->reservationId(),
                            'stockItemId' => $reservation->stockItemId()->toString(),
                        ]);

                        continue;
                    }

                    $stockReleasedEvent = new StockReleased(
                        stockItemId: $reservation->stockItemId(),
                        tenantId: $reservation->tenantId(),
                        productId: $stockItem->productId(),
                        quantity: $reservation->quantity(),
                        referenceId: $orderId,
                        reason: $reason,
                        occurredOn: new \DateTimeImmutable()
                    );
                    $this->eventDispatcher->dispatch($stockReleasedEvent);

                    ++$releasedCount;

                    $this->logger->info('Stock reservation released for cancelled order', [
                        'orderId' => $orderId,
                        'reservationId' => $reservation->reservationId(),
                        'stockItemId' => $reservation->stockItemId()->toString(),
                        'quantity' => $reservation->quantity()->value(),
                    ]);
                } catch (\Throwable $exception) {
                    ++$failedCount;

                    $this->logger->error('Failed to release reservation for cancelled order', [
                        'orderId' => $orderId,
                        'reservationId' => $reservation->reservationId(),
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            }

            $this->logger->info('Stock release processing completed for cancelled order', [
                'orderId' => $orderId,
                'totalReservations' => count($reservations),
                'releasedCount' => $releasedCount,
                'failedCount' => $failedCount,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to process stock release for cancelled order', [
                'orderId' => $event->orderId->toString(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
