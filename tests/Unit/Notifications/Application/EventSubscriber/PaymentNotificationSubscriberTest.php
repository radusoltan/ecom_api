<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Application\EventSubscriber;

use App\Notifications\Application\EventSubscriber\PaymentNotificationSubscriber;
use App\Notifications\Domain\Service\EmailSenderInterface;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PaymentNotificationSubscriberTest extends TestCase
{
    private MockObject&EmailSenderInterface $emailSender;
    private MockObject&LoggerInterface $logger;
    private PaymentNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->emailSender = $this->createMock(EmailSenderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PaymentNotificationSubscriber(
            $this->emailSender,
            $this->logger
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = PaymentNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PaymentCaptured::class, $events);
        $this->assertArrayHasKey(PaymentFailed::class, $events);
        $this->assertArrayHasKey(PaymentRefunded::class, $events);
        $this->assertEquals('onPaymentCaptured', $events[PaymentCaptured::class]);
        $this->assertEquals('onPaymentFailed', $events[PaymentFailed::class]);
        $this->assertEquals('onPaymentRefunded', $events[PaymentRefunded::class]);
    }

    public function testOnPaymentCapturedSendsEmail(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = 'order-123';
        $capturedAmountInCents = 15000;

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: $capturedAmountInCents,
            orderId: $orderId
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Payment Confirmed'),
                $this->equalTo('payment/payment_captured'),
                $this->callback(function (array $context) use ($paymentId, $orderId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['orderId'] === $orderId
                        && $context['amount'] === '150.00'
                        && $context['currency'] === 'USD';
                })
            );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Payment captured notification email sent'),
                $this->callback(function (array $context) use ($paymentId, $orderId, $tenantId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['orderId'] === $orderId
                        && $context['tenantId'] === $tenantId->toString();
                })
            );

        $this->subscriber->onPaymentCaptured($event);
    }

    public function testOnPaymentCapturedWithoutOrderId(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $capturedAmountInCents = 15000;

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: $capturedAmountInCents,
            orderId: null
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $context) {
                    return $context['orderId'] === 'N/A';
                })
            );

        $this->subscriber->onPaymentCaptured($event);
    }

    public function testOnPaymentCapturedHandlesEmailFailureGracefully(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $capturedAmountInCents = 15000;

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: $capturedAmountInCents
        );

        $exception = new \RuntimeException('Email service unavailable');

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Failed to send payment captured email'),
                $this->callback(function (array $context) use ($paymentId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['error'] === 'Email service unavailable';
                })
            );

        // Should not throw exception
        $this->subscriber->onPaymentCaptured($event);
    }

    public function testOnPaymentFailedSendsEmail(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $errorMessage = 'Insufficient funds';

        $event = new PaymentFailed(
            paymentId: $paymentId,
            tenantId: $tenantId,
            errorMessage: $errorMessage
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Payment Failed'),
                $this->equalTo('payment/payment_failed'),
                $this->callback(function (array $context) use ($paymentId, $errorMessage) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['errorMessage'] === $errorMessage;
                })
            );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Payment failed notification email sent'),
                $this->callback(function (array $context) use ($paymentId, $tenantId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['tenantId'] === $tenantId->toString();
                })
            );

        $this->subscriber->onPaymentFailed($event);
    }

    public function testOnPaymentFailedHandlesEmailFailureGracefully(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $errorMessage = 'Insufficient funds';

        $event = new PaymentFailed(
            paymentId: $paymentId,
            tenantId: $tenantId,
            errorMessage: $errorMessage
        );

        $exception = new \RuntimeException('Email service unavailable');

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Failed to send payment failed email'),
                $this->callback(function (array $context) use ($paymentId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['error'] === 'Email service unavailable';
                })
            );

        // Should not throw exception
        $this->subscriber->onPaymentFailed($event);
    }

    public function testOnPaymentRefundedSendsEmail(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $refundedAmountInCents = 15000;
        $reason = 'Customer requested refund';

        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmountInCents: $refundedAmountInCents,
            reason: $reason
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Payment Refunded'),
                $this->equalTo('payment/payment_refunded'),
                $this->callback(function (array $context) use ($paymentId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['amount'] === '150.00'
                        && $context['currency'] === 'USD';
                })
            );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Payment refunded notification email sent'),
                $this->callback(function (array $context) use ($paymentId, $tenantId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['tenantId'] === $tenantId->toString();
                })
            );

        $this->subscriber->onPaymentRefunded($event);
    }

    public function testOnPaymentRefundedHandlesEmailFailureGracefully(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $refundedAmountInCents = 15000;
        $reason = 'Customer requested refund';

        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmountInCents: $refundedAmountInCents,
            reason: $reason
        );

        $exception = new \RuntimeException('Email service unavailable');

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Failed to send payment refunded email'),
                $this->callback(function (array $context) use ($paymentId) {
                    return $context['paymentId'] === $paymentId->toString()
                        && $context['error'] === 'Email service unavailable';
                })
            );

        // Should not throw exception
        $this->subscriber->onPaymentRefunded($event);
    }
}
