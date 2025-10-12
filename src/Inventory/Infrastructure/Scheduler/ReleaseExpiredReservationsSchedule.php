<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Scheduler;

use App\Inventory\Application\Command\ReleaseExpiredReservations\ReleaseExpiredReservationsCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for releasing expired stock reservations
 *
 * Runs every minute to check for and release expired reservations.
 * Reservations expire after 15 minutes of inactivity.
 */
#[AsSchedule('inventory_reservations')]
final class ReleaseExpiredReservationsSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::every('1 minute', new ReleaseExpiredReservationsCommand())
            );
    }
}
