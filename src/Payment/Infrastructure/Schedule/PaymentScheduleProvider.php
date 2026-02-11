<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Schedule;

use App\Payment\Infrastructure\Schedule\Message\ProcessPaymentRetries;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Payment Schedule Provider.
 *
 * Defines scheduled tasks for payment processing:
 * - Process payment retries every 5 minutes
 *
 * This uses Symfony 7's Scheduler component to automatically
 * trigger retry processing at regular intervals.
 *
 * The scheduler will:
 * 1. Run every 5 minutes
 * 2. Find payments due for retry
 * 3. Attempt to reprocess them through the gateway
 * 4. Update payment status based on result
 *
 * To run the scheduler:
 * - symfony console messenger:consume scheduler_default
 *
 * Or use cron (alternative):
 * - Every 5 minutes: cd /path/to/project && php bin/console app:payment:process-retries
 */
#[AsSchedule('payment')]
final class PaymentScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run retry processing every 5 minutes
                RecurringMessage::every('5 minutes', new ProcessPaymentRetries())
            )
            // Optionally add timezone
            // ->timezone('UTC')
        ;
    }
}
