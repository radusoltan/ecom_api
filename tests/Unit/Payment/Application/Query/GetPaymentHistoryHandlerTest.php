<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\Query\GetPaymentHistory\GetPaymentHistoryHandler;
use App\Payment\Application\Query\GetPaymentHistory\GetPaymentHistoryQuery;
use App\Payment\Application\Query\GetPaymentHistory\GetPaymentHistoryResult;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetPaymentHistoryHandler::class)]
final class GetPaymentHistoryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440099';

    private PaymentRepositoryInterface $paymentRepository;
    private GetPaymentHistoryHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new GetPaymentHistoryHandler($this->paymentRepository);
    }

    // ---------------------------------------------------------------
    // Happy path — result structure
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsGetPaymentHistoryResult(): void
    {
        $this->paymentRepository
            ->method('findAllByOrderId')
            ->willReturn([]);

        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID);

        $result = ($this->handler)($query);

        self::assertInstanceOf(GetPaymentHistoryResult::class, $result);
    }

    #[Test]
    public function itReturnsEmptyResultWhenNoPaymentsExist(): void
    {
        $this->paymentRepository
            ->expects(self::once())
            ->method('findAllByOrderId')
            ->with(self::ORDER_ID)
            ->willReturn([]);

        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID, page: 1, limit: 10);

        $result = ($this->handler)($query);

        self::assertSame([], $result->payments);
        self::assertSame(0, $result->totalCount);
        self::assertSame(1, $result->currentPage);
        self::assertSame(10, $result->pageSize);
        self::assertSame(0, $result->totalPages);
    }

    #[Test]
    public function itReturnsFirstPageOfPayments(): void
    {
        $payments = $this->buildPayments(3);

        $this->paymentRepository
            ->method('findAllByOrderId')
            ->willReturn($payments);

        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID, page: 1, limit: 10);

        $result = ($this->handler)($query);

        self::assertCount(3, $result->payments);
        self::assertSame(3, $result->totalCount);
        self::assertSame(1, $result->currentPage);
        self::assertSame(1, $result->totalPages);
        self::assertContainsOnlyInstancesOf(Payment::class, $result->payments);
    }

    #[Test]
    public function itPaginatesResultsCorrectly(): void
    {
        // 5 payments, page size 2 → 3 pages
        $payments = $this->buildPayments(5);

        $this->paymentRepository
            ->method('findAllByOrderId')
            ->willReturn($payments);

        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID, page: 1, limit: 2);

        $result = ($this->handler)($query);

        self::assertCount(2, $result->payments);
        self::assertSame(5, $result->totalCount);
        self::assertSame(3, $result->totalPages);
        self::assertTrue($result->hasNextPage());
        self::assertFalse($result->hasPreviousPage());
    }

    #[Test]
    public function itReturnsSecondPage(): void
    {
        $payments = $this->buildPayments(5);

        $this->paymentRepository
            ->method('findAllByOrderId')
            ->willReturn($payments);

        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID, page: 2, limit: 2);

        $result = ($this->handler)($query);

        self::assertCount(2, $result->payments);
        self::assertSame(2, $result->currentPage);
        self::assertTrue($result->hasNextPage());
        self::assertTrue($result->hasPreviousPage());
    }

    #[Test]
    public function itReturnsPartialLastPage(): void
    {
        // 5 payments, page 3 of size 2 → only 1 item on last page
        $payments = $this->buildPayments(5);

        $this->paymentRepository
            ->method('findAllByOrderId')
            ->willReturn($payments);

        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID, page: 3, limit: 2);

        $result = ($this->handler)($query);

        self::assertCount(1, $result->payments);
        self::assertSame(3, $result->currentPage);
        self::assertFalse($result->hasNextPage());
        self::assertTrue($result->hasPreviousPage());
    }

    #[Test]
    public function itPassesOrderIdToRepository(): void
    {
        $orderId = '550e8400-e29b-41d4-a716-446655440077';

        $this->paymentRepository
            ->expects(self::once())
            ->method('findAllByOrderId')
            ->with($orderId)
            ->willReturn([]);

        $query = new GetPaymentHistoryQuery(orderId: $orderId);

        ($this->handler)($query);
    }

    #[Test]
    public function itUsesDefaultPageAndLimit(): void
    {
        $payments = $this->buildPayments(3);

        $this->paymentRepository
            ->method('findAllByOrderId')
            ->willReturn($payments);

        // Default page=1, limit=10
        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID);

        $result = ($this->handler)($query);

        self::assertSame(1, $result->currentPage);
        self::assertSame(10, $result->pageSize);
        self::assertCount(3, $result->payments); // all 3 fit in default limit of 10
    }

    #[Test]
    public function itReturnsSinglePageWhenAllPaymentsFitOnOnePage(): void
    {
        $payments = $this->buildPayments(4);

        $this->paymentRepository
            ->method('findAllByOrderId')
            ->willReturn($payments);

        $query = new GetPaymentHistoryQuery(orderId: self::ORDER_ID, page: 1, limit: 10);

        $result = ($this->handler)($query);

        self::assertSame(1, $result->totalPages);
        self::assertFalse($result->hasNextPage());
        self::assertFalse($result->hasPreviousPage());
    }

    // ---------------------------------------------------------------
    // Query validation
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenPageIsLessThanOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be >= 1');

        new GetPaymentHistoryQuery(orderId: self::ORDER_ID, page: 0);
    }

    #[Test]
    public function itThrowsWhenLimitExceedsMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        new GetPaymentHistoryQuery(orderId: self::ORDER_ID, limit: 101);
    }

    #[Test]
    public function itThrowsWhenLimitIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        new GetPaymentHistoryQuery(orderId: self::ORDER_ID, limit: 0);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    /**
     * @return Payment[]
     */
    private function buildPayments(int $count): array
    {
        $payments = [];
        for ($i = 0; $i < $count; ++$i) {
            $payments[] = Payment::create(
                id: PaymentId::generate(),
                tenantId: TenantId::fromString(self::TENANT_ID),
                orderId: self::ORDER_ID,
                amount: Money::fromScalars(1000 * ($i + 1), 'USD'),
                method: PaymentMethod::card(),
                gateway: PaymentGateway::stripe(),
            );
        }

        return $payments;
    }
}
