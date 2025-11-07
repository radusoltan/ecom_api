<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ReleaseExpiredReservations;

use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles the release of expired stock reservations.
 *
 * Business Logic:
 * 1. Find all expired reservations that haven't been released
 * 2. For each expired reservation:
 *    - Mark the reservation as released
 *    - Release the reserved quantity from the stock item
 * 3. Log the release operation for audit trail
 */
#[AsMessageHandler]
final readonly class ReleaseExpiredReservationsHandler
{
    public function __construct(
        private StockReservationRepositoryInterface $reservationRepository,
        private StockItemRepositoryInterface $stockItemRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ReleaseExpiredReservationsCommand $command): void
    {
        $expiredReservations = $this->reservationRepository->findExpiredReservations($command->now);

        $releasedCount = 0;
        $errors = [];

        foreach ($expiredReservations as $reservation) {
            try {
                // Find the stock item
                $stockItem = $this->stockItemRepository->findById($reservation->stockItemId());

                if (null === $stockItem) {
                    $this->logger->warning('Stock item not found for expired reservation', [
                        'reservationId' => $reservation->reservationId(),
                        'stockItemId' => $reservation->stockItemId()->toString(),
                    ]);

                    continue;
                }

                // Release the reserved stock
                $stockItem->release(
                    $reservation->quantity(),
                    sprintf('Reservation timeout (reservation: %s)', $reservation->reservationId())
                );

                // Save the updated stock item
                $this->stockItemRepository->save($stockItem);

                // Mark reservation as released
                $reservation->release();
                $this->reservationRepository->save($reservation);

                ++$releasedCount;

                $this->logger->info('Expired reservation released', [
                    'reservationId' => $reservation->reservationId(),
                    'stockItemId' => $reservation->stockItemId()->toString(),
                    'quantity' => $reservation->quantity()->value(),
                    'expiredAt' => $reservation->expiresAt()->format('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $exception) {
                $errors[] = [
                    'reservationId' => $reservation->reservationId(),
                    'error' => $exception->getMessage(),
                ];

                $this->logger->error('Failed to release expired reservation', [
                    'reservationId' => $reservation->reservationId(),
                    'stockItemId' => $reservation->stockItemId()->toString(),
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }
        }

        if ($releasedCount > 0 || !empty($errors)) {
            $this->logger->info('Expired reservations release completed', [
                'totalExpired' => count($expiredReservations),
                'released' => $releasedCount,
                'errors' => count($errors),
                'errorDetails' => $errors,
            ]);
        }
    }
}
