<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Shared\Infrastructure\Metrics\MetricsCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Payment Metrics Subscriber.
 *
 * Collects metrics for payment lifecycle events:
 * - payment_total (counter)
 * - payment_failed_total (counter)
 * - payment_refunded_total (counter)
 *
 * All metrics include tenant_id and gateway labels for detailed monitoring.
 *
 * @see EPIC 3.3 - Observability & Tracing
 */
final readonly class PaymentMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MetricsCollector $metricsCollector,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentAuthorized::class => 'onPaymentAuthorized',
            PaymentCaptured::class => 'onPaymentCaptured',
            PaymentFailed::class => 'onPaymentFailed',
            PaymentRefunded::class => 'onPaymentRefunded',
            PaymentCancelled::class => 'onPaymentCancelled',
        ];
    }

    public function onPaymentAuthorized(PaymentAuthorized $event): void
    {
        $this->metricsCollector->incrementCounter(
            'payment_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'status' => 'authorized',
                'gateway' => 'unknown', // Gateway info not in event, could be enhanced
            ]
        );
    }

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        $this->metricsCollector->incrementCounter(
            'payment_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'status' => 'captured',
                'gateway' => 'unknown',
            ]
        );
    }

    public function onPaymentFailed(PaymentFailed $event): void
    {
        $this->metricsCollector->incrementCounter(
            'payment_failed_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'gateway' => 'unknown',
            ]
        );
    }

    public function onPaymentRefunded(PaymentRefunded $event): void
    {
        $this->metricsCollector->incrementCounter(
            'payment_refunded_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'gateway' => 'unknown',
            ]
        );
    }

    public function onPaymentCancelled(PaymentCancelled $event): void
    {
        $this->metricsCollector->incrementCounter(
            'payment_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'status' => 'cancelled',
                'gateway' => 'unknown',
            ]
        );
    }
}
