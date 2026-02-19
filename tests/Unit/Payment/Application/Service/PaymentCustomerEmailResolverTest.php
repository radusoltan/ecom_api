<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Service;

use App\Order\Application\DTO\OrderDTO;
use App\Order\Application\Query\GetOrderByIdQuery;
use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(PaymentCustomerEmailResolver::class)]
final class PaymentCustomerEmailResolverTest extends TestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = 'b1d2e3f4-a5b6-4c7d-8e9f-a0b1c2d3e4f5';
    private const CUSTOMER_EMAIL = 'customer@example.com';

    private MessageBusInterface&MockObject $queryBus;
    private PaymentRepositoryInterface&MockObject $paymentRepository;
    private LoggerInterface&MockObject $logger;
    private PaymentCustomerEmailResolver $resolver;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->resolver = new PaymentCustomerEmailResolver(
            queryBus: $this->queryBus,
            paymentRepository: $this->paymentRepository,
            logger: $this->logger,
        );
    }

    // =========================================================================
    // resolveByOrderId — success path
    // =========================================================================

    #[Test]
    public function resolveByOrderIdReturnsCustomerEmailOnSuccess(): void
    {
        // Arrange
        $orderDTO = $this->buildOrderDTO(self::CUSTOMER_EMAIL);
        $envelope = $this->buildEnvelopeWithResult($orderDTO);

        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (GetOrderByIdQuery $query): bool {
                self::assertSame(self::ORDER_ID, $query->orderId);
                self::assertSame(self::DEFAULT_TENANT_ID, $query->tenantId);

                return true;
            }))
            ->willReturn($envelope);

        $this->logger->expects(self::never())->method('warning');
        $this->logger->expects(self::never())->method('error');

        // Act
        $result = $this->resolver->resolveByOrderId(self::ORDER_ID, self::DEFAULT_TENANT_ID);

        // Assert
        self::assertSame(self::CUSTOMER_EMAIL, $result);
    }

    // =========================================================================
    // resolveByOrderId — no HandledStamp on envelope
    // =========================================================================

    #[Test]
    public function resolveByOrderIdReturnsNullWhenNoHandledStamp(): void
    {
        // Arrange — envelope carries no stamps at all
        $emptyEnvelope = new Envelope(new \stdClass(), []);

        $this->queryBus
            ->method('dispatch')
            ->willReturn($emptyEnvelope);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'No handler result for GetOrderByIdQuery',
                ['order_id' => self::ORDER_ID],
            );

        // Act
        $result = $this->resolver->resolveByOrderId(self::ORDER_ID, self::DEFAULT_TENANT_ID);

        // Assert
        self::assertNull($result);
    }

    // =========================================================================
    // resolveByOrderId — handler returns null (order not found)
    // =========================================================================

    #[Test]
    public function resolveByOrderIdReturnsNullWhenOrderNotFound(): void
    {
        // Arrange — HandledStamp wraps a null result (order does not exist)
        $envelope = $this->buildEnvelopeWithResult(null);

        $this->queryBus
            ->method('dispatch')
            ->willReturn($envelope);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Order not found for email resolution',
                ['order_id' => self::ORDER_ID],
            );

        // Act
        $result = $this->resolver->resolveByOrderId(self::ORDER_ID, self::DEFAULT_TENANT_ID);

        // Assert
        self::assertNull($result);
    }

    // =========================================================================
    // resolveByOrderId — query bus throws
    // =========================================================================

    #[Test]
    public function resolveByOrderIdReturnsNullAndLogsErrorOnException(): void
    {
        // Arrange
        $exception = new \RuntimeException('Query bus unavailable');

        $this->queryBus
            ->method('dispatch')
            ->willThrowException($exception);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to resolve customer email from order',
                [
                    'order_id' => self::ORDER_ID,
                    'error' => 'Query bus unavailable',
                ],
            );

        // Act
        $result = $this->resolver->resolveByOrderId(self::ORDER_ID, self::DEFAULT_TENANT_ID);

        // Assert
        self::assertNull($result);
    }

    // =========================================================================
    // resolveByOrderId — propagates correct tenantId string to query
    // =========================================================================

    #[Test]
    public function resolveByOrderIdPassesTenantIdStringToQuery(): void
    {
        // Arrange — use a different (non-default) tenant to confirm parameter routing
        $differentTenantId = 'a1b2c3d4-e5f6-4a7b-8c9d-e0f1a2b3c4d5';
        $orderDTO = $this->buildOrderDTO('another@example.com');
        $envelope = $this->buildEnvelopeWithResult($orderDTO);

        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (GetOrderByIdQuery $query) use ($differentTenantId): bool {
                self::assertSame($differentTenantId, $query->tenantId);

                return true;
            }))
            ->willReturn($envelope);

        // Act
        $result = $this->resolver->resolveByOrderId(self::ORDER_ID, $differentTenantId);

        // Assert
        self::assertSame('another@example.com', $result);
    }

    // =========================================================================
    // resolveByPaymentId — full success path
    // =========================================================================

    #[Test]
    public function resolveByPaymentIdReturnsCustomerEmailOnSuccess(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $payment = $this->buildPayment(self::ORDER_ID);
        $orderDTO = $this->buildOrderDTO(self::CUSTOMER_EMAIL);
        $envelope = $this->buildEnvelopeWithResult($orderDTO);

        $this->paymentRepository
            ->expects(self::once())
            ->method('findById')
            ->with($paymentId)
            ->willReturn($payment);

        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (GetOrderByIdQuery $query) use ($tenantId): bool {
                self::assertSame(self::ORDER_ID, $query->orderId);
                self::assertSame($tenantId->toString(), $query->tenantId);

                return true;
            }))
            ->willReturn($envelope);

        $this->logger->expects(self::never())->method('warning');
        $this->logger->expects(self::never())->method('error');

        // Act
        $result = $this->resolver->resolveByPaymentId($paymentId, $tenantId);

        // Assert
        self::assertSame(self::CUSTOMER_EMAIL, $result);
    }

    // =========================================================================
    // resolveByPaymentId — payment not found in repository
    // =========================================================================

    #[Test]
    public function resolveByPaymentIdReturnsNullWhenPaymentNotFound(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);

        $this->paymentRepository
            ->method('findById')
            ->willReturn(null);

        $this->queryBus->expects(self::never())->method('dispatch');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Payment not found for email resolution',
                ['payment_id' => $paymentId->toString()],
            );

        // Act
        $result = $this->resolver->resolveByPaymentId($paymentId, $tenantId);

        // Assert
        self::assertNull($result);
    }

    // =========================================================================
    // resolveByPaymentId — payment found but order lookup fails (order not found)
    // =========================================================================

    #[Test]
    public function resolveByPaymentIdReturnsNullWhenOrderLookupReturnsNull(): void
    {
        // Arrange — payment exists, but the query handler returns null for the order
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $payment = $this->buildPayment(self::ORDER_ID);
        $envelope = $this->buildEnvelopeWithResult(null);

        $this->paymentRepository
            ->method('findById')
            ->willReturn($payment);

        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn($envelope);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Order not found for email resolution',
                ['order_id' => self::ORDER_ID],
            );

        // Act
        $result = $this->resolver->resolveByPaymentId($paymentId, $tenantId);

        // Assert
        self::assertNull($result);
    }

    // =========================================================================
    // resolveByPaymentId — repository throws
    // =========================================================================

    #[Test]
    public function resolveByPaymentIdReturnsNullAndLogsErrorOnRepositoryException(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $exception = new \RuntimeException('Database connection lost');

        $this->paymentRepository
            ->method('findById')
            ->willThrowException($exception);

        $this->queryBus->expects(self::never())->method('dispatch');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to resolve customer email from payment',
                [
                    'payment_id' => $paymentId->toString(),
                    'error' => 'Database connection lost',
                ],
            );

        // Act
        $result = $this->resolver->resolveByPaymentId($paymentId, $tenantId);

        // Assert
        self::assertNull($result);
    }

    // =========================================================================
    // resolveByPaymentId — payment found but query bus throws (nested exception)
    // =========================================================================

    #[Test]
    public function resolveByPaymentIdReturnsNullWhenQueryBusThrowsAfterPaymentFound(): void
    {
        // Arrange — repository succeeds, query bus fails on the inner resolveByOrderId call.
        // The outer catch in resolveByPaymentId swallows it and logs 'Failed to resolve
        // customer email from payment' (not 'from order') because the inner method's own
        // try/catch returns null without re-throwing, so no outer exception occurs here.
        // Verify that the inner error path is reached correctly.
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $payment = $this->buildPayment(self::ORDER_ID);

        $this->paymentRepository
            ->method('findById')
            ->willReturn($payment);

        // Inner resolveByOrderId's try/catch will catch this and log 'from order',
        // returning null — the outer method then simply returns null itself.
        $this->queryBus
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Bus handler timeout'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to resolve customer email from order',
                [
                    'order_id' => self::ORDER_ID,
                    'error' => 'Bus handler timeout',
                ],
            );

        // Act
        $result = $this->resolver->resolveByPaymentId($paymentId, $tenantId);

        // Assert
        self::assertNull($result);
    }

    // =========================================================================
    // resolveByOrderId — verifies the email value is taken verbatim from the DTO
    // =========================================================================

    #[Test]
    public function resolveByOrderIdReturnsExactEmailFromOrderDTO(): void
    {
        // Arrange — confirm the raw property value is returned unchanged
        $expectedEmail = 'UPPER.Case+alias@sub.domain.io';
        $orderDTO = $this->buildOrderDTO($expectedEmail);
        $envelope = $this->buildEnvelopeWithResult($orderDTO);

        $this->queryBus
            ->method('dispatch')
            ->willReturn($envelope);

        // Act
        $result = $this->resolver->resolveByOrderId(self::ORDER_ID, self::DEFAULT_TENANT_ID);

        // Assert
        self::assertSame($expectedEmail, $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an Envelope carrying a HandledStamp that wraps the given result.
     */
    private function buildEnvelopeWithResult(mixed $result): Envelope
    {
        $stamp = new HandledStamp($result, 'handler_name');

        return new Envelope(new \stdClass(), [$stamp]);
    }

    /**
     * Build a real OrderDTO with a specific customerEmail.
     * All other fields are filled with neutral test values.
     */
    private function buildOrderDTO(string $customerEmail): OrderDTO
    {
        return new OrderDTO(
            id: self::ORDER_ID,
            tenantId: self::DEFAULT_TENANT_ID,
            customerEmail: $customerEmail,
            status: 'placed',
            lines: [],
            shippingAddress: [
                'street' => '1 Test St',
                'city' => 'Testville',
                'state' => 'TS',
                'postalCode' => '00000',
                'country' => 'US',
            ],
            billingAddress: [
                'street' => '1 Test St',
                'city' => 'Testville',
                'state' => 'TS',
                'postalCode' => '00000',
                'country' => 'US',
            ],
            totalAmount: 9999,
            totalCurrency: 'USD',
            appliedPromotions: [],
            discountAmount: null,
            discountCurrency: null,
            couponCode: null,
            taxAmount: null,
            taxCurrency: null,
            taxJurisdiction: null,
            taxRuleId: null,
            taxRate: 0.0,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00',
        );
    }

    /**
     * Build a real Payment domain model with the given orderId.
     * Uses Payment::create() — the standard factory — so no bypassing is required.
     */
    private function buildPayment(string $orderId): Payment
    {
        return Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::DEFAULT_TENANT_ID),
            orderId: $orderId,
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );
    }
}
