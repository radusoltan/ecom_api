<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ReleaseExpiredReservations;

/**
 * Command to release all expired stock reservations
 *
 * This command should be executed periodically (e.g., every minute via cron/scheduler)
 * to automatically release reservations that have exceeded the 15-minute timeout.
 */
final readonly class ReleaseExpiredReservationsCommand
{
    public function __construct(
        public ?\DateTimeImmutable $now = null
    ) {
    }
}
