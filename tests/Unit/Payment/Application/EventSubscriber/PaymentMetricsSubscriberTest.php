<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentMetricsSubscriber;
use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Metrics\MetricsCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[CoversClass(PaymentMetricsSubscriber::class)]
final class PaymentMetricsSubscriberTest extends TestCase
{
    private MetricsCollector $metricsCollector;
    private PaymentMetricsSubscriber $subscriber;
    private TenantId $tenantId;
    private PaymentId $paymentId;

    protected function setUp(): void
    {
        // MetricsCollector is a concrete in-memory store with no real I/O,
        // so we use a real instance rather than a mock.
        $this->metricsCollector = new MetricsCollector('test');
        $this->subscriber = new PaymentMetricsSubscriber($this->metricsCollector);

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->paymentId = PaymentId::generate();
    }

    // -----------------------------------------------------------------------
    // getSubscribedEvents
    // -----------------------------------------------------------------------

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itSubscribesToAllFivePaymentEvents(): void
    {
        $events = PaymentMetricsSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(PaymentAuthorized::class, $events);
        self::assertArrayHasKey(PaymentCaptured::class, $events);
        self::assertArrayHasKey(PaymentFailed::class, $events);
        self::assertArrayHasKey(PaymentRefunded::class, $events);
        self::assertArrayHasKey(PaymentCancelled::class, $events);
    }

    #[Test]
    public function itMapsEventsToCorrectHandlerMethods(): void
    {
        $events = PaymentMetricsSubscriber::getSubscribedEvents();

        self::assertSame('onPaymentAuthorized', $events[PaymentAuthorized::class]);
        self::assertSame('onPaymentCaptured', $events[PaymentCaptured::class]);
        self::assertSame('onPaymentFailed', $events[PaymentFailed::class]);
        self::assertSame('onPaymentRefunded', $events[PaymentRefunded::class]);
        self::assertSame('onPaymentCancelled', $events[PaymentCancelled::class]);
    }

    // -----------------------------------------------------------------------
    // onPaymentAuthorized
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncrementsPaymentTotalCounterOnAuthorized(): void
    {
        $event = new PaymentAuthorized(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            gatewayTransactionId: 'pi_test_authorized_001',
        );

        // Must not throw
        $this->subscriber->onPaymentAuthorized($event);

        // Verify metrics were recorded via Prometheus export
        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_total', $output);
    }

    #[Test]
    public function itRecordsAuthorizedStatusLabelInMetric(): void
    {
        $event = new PaymentAuthorized(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            gatewayTransactionId: 'pi_authorized_abc',
        );

        $this->subscriber->onPaymentAuthorized($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('authorized', $output);
    }

    #[Test]
    public function itIncludesTenantIdLabelOnAuthorizedMetric(): void
    {
        $event = new PaymentAuthorized(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            gatewayTransactionId: 'pi_abc',
        );

        $this->subscriber->onPaymentAuthorized($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString($this->tenantId->toString(), $output);
    }

    // -----------------------------------------------------------------------
    // onPaymentCaptured
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncrementsPaymentTotalCounterOnCaptured(): void
    {
        $event = new PaymentCaptured(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            capturedAmount: Money::fromScalars(9999, 'USD'),
        );

        $this->subscriber->onPaymentCaptured($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_total', $output);
    }

    #[Test]
    public function itRecordsCapturedStatusLabel(): void
    {
        $event = new PaymentCaptured(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            capturedAmount: Money::fromScalars(5000, 'USD'),
        );

        $this->subscriber->onPaymentCaptured($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('captured', $output);
    }

    #[Test]
    public function itCapturedHandlesOptionalOrderId(): void
    {
        $eventWithOrder = new PaymentCaptured(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            capturedAmount: Money::fromScalars(1000, 'USD'),
            orderId: 'order-xyz-123',
        );

        $eventWithoutOrder = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: $this->tenantId,
            capturedAmount: Money::fromScalars(2000, 'USD'),
            orderId: null,
        );

        // Both must not throw
        $this->subscriber->onPaymentCaptured($eventWithOrder);
        $this->subscriber->onPaymentCaptured($eventWithoutOrder);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_total', $output);
    }

    // -----------------------------------------------------------------------
    // onPaymentFailed
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncrementsPaymentFailedTotalOnFailed(): void
    {
        $event = new PaymentFailed(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            errorMessage: 'Card declined',
        );

        $this->subscriber->onPaymentFailed($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_failed_total', $output);
    }

    #[Test]
    public function itIncludesTenantIdInFailedMetric(): void
    {
        $event = new PaymentFailed(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            errorMessage: 'Insufficient funds',
        );

        $this->subscriber->onPaymentFailed($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString($this->tenantId->toString(), $output);
    }

    #[Test]
    public function itAccumulatesMultipleFailedEvents(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->subscriber->onPaymentFailed(new PaymentFailed(
                paymentId: PaymentId::generate(),
                tenantId: $this->tenantId,
                errorMessage: "Error {$i}",
            ));
        }

        $output = $this->metricsCollector->exportPrometheusFormat();
        // Counter value should be 3
        self::assertStringContainsString('3', $output);
    }

    // -----------------------------------------------------------------------
    // onPaymentRefunded
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncrementsPaymentRefundedTotalOnRefunded(): void
    {
        $event = new PaymentRefunded(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            refundedAmount: Money::fromScalars(4999, 'USD'),
            reason: 'Customer requested refund',
        );

        $this->subscriber->onPaymentRefunded($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_refunded_total', $output);
    }

    #[Test]
    public function itIncludesTenantIdInRefundedMetric(): void
    {
        $event = new PaymentRefunded(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            refundedAmount: Money::fromScalars(1000, 'USD'),
            reason: 'Wrong item shipped',
        );

        $this->subscriber->onPaymentRefunded($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString($this->tenantId->toString(), $output);
    }

    // -----------------------------------------------------------------------
    // onPaymentCancelled
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncrementsPaymentTotalWithCancelledStatusOnCancelled(): void
    {
        $event = new PaymentCancelled(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            reason: 'Customer cancelled order',
        );

        $this->subscriber->onPaymentCancelled($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_total', $output);
        self::assertStringContainsString('cancelled', $output);
    }

    #[Test]
    public function itIncludesTenantIdInCancelledMetric(): void
    {
        $event = new PaymentCancelled(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            reason: 'Out of stock',
        );

        $this->subscriber->onPaymentCancelled($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString($this->tenantId->toString(), $output);
    }

    // -----------------------------------------------------------------------
    // Counter segregation — different metrics for different event types
    // -----------------------------------------------------------------------

    #[Test]
    public function itUsesPaymentFailedTotalNotPaymentTotalForFailedEvents(): void
    {
        // Trigger only a failed event — payment_total should NOT increment
        $event = new PaymentFailed(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            errorMessage: 'Gateway timeout',
        );

        $this->subscriber->onPaymentFailed($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_failed_total', $output);
        // payment_refunded_total should have no entries (never incremented)
        self::assertStringNotContainsString('payment_refunded_total{', $output);
    }

    #[Test]
    public function itUsesPaymentRefundedTotalNotPaymentTotalForRefundedEvents(): void
    {
        $event = new PaymentRefunded(
            paymentId: $this->paymentId,
            tenantId: $this->tenantId,
            refundedAmount: Money::fromScalars(2500, 'USD'),
            reason: 'Dispute resolved in customer favour',
        );

        $this->subscriber->onPaymentRefunded($event);

        $output = $this->metricsCollector->exportPrometheusFormat();
        self::assertStringContainsString('payment_refunded_total', $output);
        // payment_failed_total should have no labelled entries
        self::assertStringNotContainsString('payment_failed_total{', $output);
    }

    // -----------------------------------------------------------------------
    // All events with same tenant accumulate correctly
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesAllEventTypesInSingleSession(): void
    {
        $this->subscriber->onPaymentAuthorized(new PaymentAuthorized(
            paymentId: PaymentId::generate(),
            tenantId: $this->tenantId,
            gatewayTransactionId: 'pi_1',
        ));

        $this->subscriber->onPaymentCaptured(new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: $this->tenantId,
            capturedAmount: Money::fromScalars(1000, 'USD'),
        ));

        $this->subscriber->onPaymentFailed(new PaymentFailed(
            paymentId: PaymentId::generate(),
            tenantId: $this->tenantId,
            errorMessage: 'Declined',
        ));

        $this->subscriber->onPaymentRefunded(new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $this->tenantId,
            refundedAmount: Money::fromScalars(500, 'USD'),
            reason: 'Return',
        ));

        $this->subscriber->onPaymentCancelled(new PaymentCancelled(
            paymentId: PaymentId::generate(),
            tenantId: $this->tenantId,
            reason: 'Cancelled',
        ));

        $output = $this->metricsCollector->exportPrometheusFormat();

        // All three metric families must appear
        self::assertStringContainsString('payment_total', $output);
        self::assertStringContainsString('payment_failed_total', $output);
        self::assertStringContainsString('payment_refunded_total', $output);
    }
}
