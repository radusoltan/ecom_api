<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\Query\GetTransactionsByPayment\GetTransactionsByPaymentHandler;
use App\Payment\Application\Query\GetTransactionsByPayment\GetTransactionsByPaymentQuery;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Model\TransactionId;
use App\Payment\Domain\Model\TransactionType;
use App\Payment\Domain\Repository\TransactionRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetTransactionsByPaymentHandler::class)]
final class GetTransactionsByPaymentHandlerTest extends TestCase
{
    private TransactionRepositoryInterface $transactionRepository;
    private GetTransactionsByPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);
        $this->handler = new GetTransactionsByPaymentHandler($this->transactionRepository);
    }

    // ---------------------------------------------------------------
    // Happy path — results returned
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsTransactionsForPayment(): void
    {
        $paymentId = PaymentId::generate();

        $transactions = [
            $this->buildAuthorizationTransaction($paymentId),
            $this->buildCaptureTransaction($paymentId),
        ];

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByPaymentId')
            ->with(self::identicalTo($paymentId))
            ->willReturn($transactions);

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(Transaction::class, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenPaymentHasNoTransactions(): void
    {
        $paymentId = PaymentId::generate();

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByPaymentId')
            ->with(self::identicalTo($paymentId))
            ->willReturn([]);

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsSingleTransactionWhenOnlyOneExists(): void
    {
        $paymentId = PaymentId::generate();
        $transaction = $this->buildAuthorizationTransaction($paymentId);

        $this->transactionRepository
            ->method('findByPaymentId')
            ->willReturn([$transaction]);

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertSame($transaction, $result[0]);
    }

    // ---------------------------------------------------------------
    // Repository delegation
    // ---------------------------------------------------------------

    #[Test]
    public function itPassesPaymentIdDirectlyToRepository(): void
    {
        $paymentId = PaymentId::generate();

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByPaymentId')
            ->with(self::callback(
                fn (PaymentId $id): bool => $id->equals($paymentId),
            ))
            ->willReturn([]);

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        ($this->handler)($query);
    }

    #[Test]
    public function itDelegatesOnlyToFindByPaymentId(): void
    {
        $paymentId = PaymentId::generate();

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByPaymentId');

        $this->transactionRepository->expects(self::never())->method('findById');
        $this->transactionRepository->expects(self::never())->method('save');
        $this->transactionRepository->expects(self::never())->method('findByGatewayTransactionId');

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        ($this->handler)($query);
    }

    // ---------------------------------------------------------------
    // Result correctness — domain model fields preserved
    // ---------------------------------------------------------------

    #[Test]
    public function itPreservesTransactionFieldsFromRepository(): void
    {
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(5000, 'USD');
        $gatewayTxId = 'pi_test_abc123';

        $transaction = Transaction::createAuthorization(
            id: TransactionId::generate(),
            paymentId: $paymentId,
            amount: $amount,
            gatewayTransactionId: $gatewayTxId,
            gatewayResponse: '{"status":"authorized"}',
        );

        $this->transactionRepository
            ->method('findByPaymentId')
            ->willReturn([$transaction]);

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);

        $fetched = $result[0];
        self::assertTrue($fetched->paymentId()->equals($paymentId));
        self::assertSame(5000, $fetched->amount()->getAmount());
        self::assertSame('USD', $fetched->amount()->getCurrency()->getCurrencyCode());
        self::assertSame($gatewayTxId, $fetched->gatewayTransactionId());
        self::assertTrue($fetched->isAuthorization());
        self::assertTrue($fetched->isSuccessful());
        self::assertSame('{"status":"authorized"}', $fetched->gatewayResponse());
    }

    #[Test]
    public function itReturnsFailedTransactionCorrectly(): void
    {
        $paymentId = PaymentId::generate();

        $transaction = Transaction::createFailed(
            id: TransactionId::generate(),
            paymentId: $paymentId,
            type: TransactionType::CAPTURE,
            amount: Money::fromScalars(9999, 'EUR'),
            errorCode: 'insufficient_funds',
            errorMessage: 'Card has insufficient funds',
        );

        $this->transactionRepository
            ->method('findByPaymentId')
            ->willReturn([$transaction]);

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertTrue($result[0]->isFailed());
        self::assertTrue($result[0]->isCapture());
        self::assertSame('insufficient_funds', $result[0]->errorCode());
        self::assertSame('Card has insufficient funds', $result[0]->errorMessage());
    }

    #[Test]
    public function itReturnsMultipleTransactionTypesInOrder(): void
    {
        $paymentId = PaymentId::generate();

        $authorization = $this->buildAuthorizationTransaction($paymentId);
        $capture = $this->buildCaptureTransaction($paymentId);
        $refund = Transaction::createRefund(
            id: TransactionId::generate(),
            paymentId: $paymentId,
            amount: Money::fromScalars(5000, 'USD'),
            gatewayTransactionId: 're_test_refund',
        );

        // Repository returns them in creation order (oldest first)
        $this->transactionRepository
            ->method('findByPaymentId')
            ->willReturn([$authorization, $capture, $refund]);

        $query = new GetTransactionsByPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertCount(3, $result);
        self::assertTrue($result[0]->isAuthorization());
        self::assertTrue($result[1]->isCapture());
        self::assertTrue($result[2]->isRefund());
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildAuthorizationTransaction(PaymentId $paymentId): Transaction
    {
        return Transaction::createAuthorization(
            id: TransactionId::generate(),
            paymentId: $paymentId,
            amount: Money::fromScalars(10000, 'USD'),
            gatewayTransactionId: 'pi_test_auth_'.bin2hex(random_bytes(4)),
        );
    }

    private function buildCaptureTransaction(PaymentId $paymentId): Transaction
    {
        return Transaction::createCapture(
            id: TransactionId::generate(),
            paymentId: $paymentId,
            amount: Money::fromScalars(10000, 'USD'),
            gatewayTransactionId: 'pi_test_cap_'.bin2hex(random_bytes(4)),
        );
    }
}
