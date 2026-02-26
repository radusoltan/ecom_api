<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\EventSubscriber;

use App\Order\Domain\ValueObject\OrderProductId;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceCommand;
use App\Invoice\Application\Command\IssueInvoice\IssueInvoiceCommand;
use App\Invoice\Application\EventSubscriber\PaymentCompletedInvoiceSubscriber;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PaymentCompletedInvoiceSubscriber::class)]
final class PaymentCompletedInvoiceSubscriberTest extends TestCase
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
    public function itPassesReverseChargeToInvoiceGeneration(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $invoiceId = InvoiceId::generate();

        $order = $this->createReverseChargeOrder($orderId, $tenantId);
        $event = $this->createPaymentCapturedEvent($tenantId, $orderId->toString());

        $this->invoiceRepository
            ->method('findByOrderId')
            ->willReturn(null);

        $this->invoiceRepository
            ->method('nextIdentity')
            ->willReturn($invoiceId);

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $dispatchedCommands = [];
        $this->commandBus
            ->method('dispatch')
            ->willReturnCallback(function ($command) use (&$dispatchedCommands): Envelope {
                $dispatchedCommands[] = $command;

                return new Envelope($command);
            });

        $this->subscriber->onPaymentCaptured($event);

        // First command should be GenerateInvoiceCommand
        $this->assertCount(2, $dispatchedCommands);
        $generateCommand = $dispatchedCommands[0];
        $this->assertInstanceOf(GenerateInvoiceCommand::class, $generateCommand);
        $this->assertTrue($generateCommand->isReverseCharge);

        // Billing address should include VAT number
        $this->assertSame('DE123456789', $generateCommand->billingAddress['vatNumber']);

        // Notes should include reverse charge legal notice
        $this->assertNotNull($generateCommand->notes);
        $this->assertStringContainsString('Reverse charge, Art. 196 Dir. 2006/112/EC', $generateCommand->notes);
        $this->assertStringContainsString('Buyer VAT: DE123456789', $generateCommand->notes);
    }

    #[Test]
    public function itPassesFalseReverseChargeForB2COrder(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $invoiceId = InvoiceId::generate();

        $order = $this->createB2COrder($orderId, $tenantId);
        $event = $this->createPaymentCapturedEvent($tenantId, $orderId->toString());

        $this->invoiceRepository
            ->method('findByOrderId')
            ->willReturn(null);

        $this->invoiceRepository
            ->method('nextIdentity')
            ->willReturn($invoiceId);

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $dispatchedCommands = [];
        $this->commandBus
            ->method('dispatch')
            ->willReturnCallback(function ($command) use (&$dispatchedCommands): Envelope {
                $dispatchedCommands[] = $command;

                return new Envelope($command);
            });

        $this->subscriber->onPaymentCaptured($event);

        $generateCommand = $dispatchedCommands[0];
        $this->assertInstanceOf(GenerateInvoiceCommand::class, $generateCommand);
        $this->assertFalse($generateCommand->isReverseCharge);
        $this->assertNull($generateCommand->billingAddress['vatNumber']);

        // Notes should NOT contain reverse charge notice
        if (null !== $generateCommand->notes) {
            $this->assertStringNotContainsString('Reverse charge', $generateCommand->notes);
        }
    }

    #[Test]
    public function itSubscribesToPaymentCapturedEvent(): void
    {
        $events = PaymentCompletedInvoiceSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PaymentCaptured::class, $events);
        $this->assertSame('onPaymentCaptured', $events[PaymentCaptured::class]);
    }

    private function createReverseChargeOrder(OrderId $orderId, TenantId $tenantId): Order
    {
        $address = Address::create('123 Hauptstr.', 'Berlin', 'BE', '10115', 'DE');

        $lines = [
            OrderLine::create(
                OrderProductId::generate(),
                'Widget Pro',
                2,
                Money::fromScalars(5000, 'EUR')
            ),
        ];

        return Order::reconstituteFromPersistence(
            id: $orderId,
            tenantId: $tenantId,
            customerEmail: 'buyer@acme.fr',
            status: \App\Order\Domain\Model\OrderStatus::paid(),
            lines: $lines,
            shippingAddress: $address,
            billingAddress: $address,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            appliedPromotions: [],
            discountAmount: null,
            couponCode: null,
            taxAmount: null,
            taxJurisdiction: 'FR',
            taxRuleId: null,
            taxRate: 0.0,
            isReverseCharge: true,
            vatNumber: 'DE123456789',
        );
    }

    private function createB2COrder(OrderId $orderId, TenantId $tenantId): Order
    {
        $address = Address::create('123 Test St', 'Test City', 'CA', '12345', 'US');

        $lines = [
            OrderLine::create(
                OrderProductId::generate(),
                'Widget Basic',
                1,
                Money::fromScalars(2000, 'USD')
            ),
        ];

        return Order::reconstituteFromPersistence(
            id: $orderId,
            tenantId: $tenantId,
            customerEmail: 'customer@example.com',
            status: \App\Order\Domain\Model\OrderStatus::paid(),
            lines: $lines,
            shippingAddress: $address,
            billingAddress: $address,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            appliedPromotions: [],
            discountAmount: null,
            couponCode: null,
            taxAmount: Money::fromScalars(200, 'USD'),
            taxJurisdiction: 'US-CA',
            taxRuleId: 'rule-us',
            taxRate: 10.0,
        );
    }

    private function createPaymentCapturedEvent(TenantId $tenantId, string $orderId): PaymentCaptured
    {
        return new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            capturedAmountInCents: 10000,
            orderId: $orderId,
        );
    }
}
