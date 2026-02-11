<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventSubscriber;

use App\Inventory\Domain\Event\StockReleased;
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
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
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
                $event->reason ? ': ' . $event->reason : ''
            );

            $this->logger->info('Processing stock release for cancelled order', [
                'orderId' => $orderId,
                'tenantId' => $event->tenantId->toString(),
                'reason' => $event->reason,
            ]);

            // Find all reservations for this order
            $reservations = $this->reservationRepository->findByOrderId($orderId);

            if (empty($reservations)) {
                $this->logger->info('No stock reservations found for cancelled order', [
                    'orderId' => $orderId,
                ]);

                return;
            }

            $releasedCount = 0;
            $failedCount = 0;

            // Release each reservation
            foreach ($reservations as $reservation) {
                try {
                    // Skip if already released
                    if ($reservation->isReleased()) {
                        $this->logger->debug('Reservation already released, skipping', [
                            'orderId' => $orderId,
                            'reservationId' => $reservation->reservationId(),
                        ]);
                        continue;
                    }

                    // Release the reservation
                    $reservation->release();
                    $this->reservationRepository->save($reservation);

                    // Emit StockReleased event
                    $stockReleasedEvent = new StockReleased(
                        stockItemId: $reservation->stockItemId(),
                        quantity: $reservation->quantity(),
                        referenceId: $orderId,
                        reason: $reason,
                        occurredOn: new \DateTimeImmutable()
                    );
                    $this->eventDispatcher->dispatch($stockReleasedEvent);

                    $releasedCount++;

                    $this->logger->info('Stock reservation released for cancelled order', [
                        'orderId' => $orderId,
                        'reservationId' => $reservation->reservationId(),
                        'stockItemId' => $reservation->stockItemId()->toString(),
                        'quantity' => $reservation->quantity()->value(),
                    ]);
                } catch (\Throwable $exception) {
                    $failedCount++;

                    // Log error but continue processing other reservations
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
            // Log error but don't throw - stock release failure shouldn't block order cancellation
            $this->logger->error('Failed to process stock release for cancelled order', [
                'orderId' => $event->orderId->toString(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
