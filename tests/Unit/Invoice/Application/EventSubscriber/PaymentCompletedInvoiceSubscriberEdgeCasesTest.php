<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\EventSubscriber;

use App\Invoice\Application\EventSubscriber\PaymentCompletedInvoiceSubscriber;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PaymentCompletedInvoiceSubscriber::class)]
final class PaymentCompletedInvoiceSubscriberEdgeCasesTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private InvoiceRepositoryInterface $invoiceRepository;
    private OrderRepositoryInterface $orderRepository;
    private LoggerInterface $logger;
    private PaymentCompletedInvoiceSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PaymentCompletedInvoiceSubscriber(
            commandBus: $this->commandBus,
            invoiceRepository: $this->invoiceRepository,
            orderRepository: $this->orderRepository,
            logger: $this->logger,
        );
    }

    #[Test]
    public function itSkipsInvoiceGenerationWhenOrderIdIsNull(): void
    {
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: null,
        );

        $this->commandBus->expects(self::never())->method('dispatch');
        $this->invoiceRepository->expects(self::never())->method('findByOrderId');

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('no associated order'),
                self::arrayHasKey('paymentId'),
            );

        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function itSkipsInvoiceGenerationWhenInvoiceAlreadyExists(): void
    {
        $tenantId = TenantId::generate();
        $orderId = \App\Order\Domain\Model\OrderId::generate();

        $existingInvoice = $this->createStubInvoice();

        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: $orderId->toString(),
        );

        $this->invoiceRepository
            ->expects(self::once())
            ->method('findByOrderId')
            ->willReturn($existingInvoice);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('Invoice already exists'),
                self::arrayHasKey('orderId'),
            );

        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function itSkipsInvoiceGenerationWhenOrderNotFound(): void
    {
        $tenantId = TenantId::generate();
        $orderId = \App\Order\Domain\Model\OrderId::generate();

        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: $orderId->toString(),
        );

        $this->invoiceRepository
            ->method('findByOrderId')
            ->willReturn(null);

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn(null);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Order not found'),
                self::arrayHasKey('orderId'),
            );

        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function itLogsErrorAndDoesNotThrowWhenExceptionOccurs(): void
    {
        $tenantId = TenantId::generate();
        $orderId = \App\Order\Domain\Model\OrderId::generate();

        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: $orderId->toString(),
        );

        $this->invoiceRepository
            ->method('findByOrderId')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to generate invoice'),
                self::arrayHasKey('error'),
            );

        // Must not throw - failures are swallowed to protect the payment flow
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function itReturnsCorrectSubscribedEvents(): void
    {
        $events = PaymentCompletedInvoiceSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(PaymentCaptured::class, $events);
        self::assertSame('onPaymentCaptured', $events[PaymentCaptured::class]);
        self::assertCount(1, $events);
    }

    private function createStubInvoice(): Invoice
    {
        return Invoice::createDraft(
            InvoiceId::generate(),
            TenantId::generate(),
            \App\Order\Domain\Model\OrderId::generate(),
            \App\Customer\Domain\ValueObject\CustomerId::generate(),
            BillingAddress::create('Test Name', '123 Test Street', null, 'Test City', '12345', 'US'),
        );
    }
}
