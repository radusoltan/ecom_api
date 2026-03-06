<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Service;

use App\Payment\Domain\Service\RefundResult;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundResult::class)]
final class RefundResultTest extends TestCase
{
    private function makeMoney(int $cents = 5000, string $currency = 'USD'): Money
    {
        return Money::fromScalars($cents, $currency);
    }

    // ---------------------------------------------------------------
    // success()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesSuccessResult(): void
    {
        $amount = $this->makeMoney(5000, 'USD');

        $result = RefundResult::success(
            gatewayRefundId: 're_stripe_abc123',
            status: 'succeeded',
            amount: $amount,
        );

        self::assertTrue($result->success);
        self::assertSame('re_stripe_abc123', $result->gatewayRefundId);
        self::assertSame('succeeded', $result->status);
        self::assertSame($amount, $result->amount);
        self::assertNull($result->errorCode);
        self::assertNull($result->errorMessage);
        self::assertNull($result->rawResponse);
    }

    #[Test]
    public function itCreatesSuccessResultWithRawResponse(): void
    {
        $result = RefundResult::success(
            gatewayRefundId: 're_xyz789',
            status: 'pending',
            amount: $this->makeMoney(2000, 'EUR'),
            rawResponse: '{"id":"re_xyz789","status":"pending"}',
        );

        self::assertSame('re_xyz789', $result->gatewayRefundId);
        self::assertSame('{"id":"re_xyz789","status":"pending"}', $result->rawResponse);
    }

    // ---------------------------------------------------------------
    // failure()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFailureResult(): void
    {
        $result = RefundResult::failure(
            errorCode: 'charge_already_refunded',
            errorMessage: 'The payment has already been fully refunded',
        );

        self::assertFalse($result->success);
        self::assertNull($result->gatewayRefundId);
        self::assertSame('failed', $result->status);
        self::assertSame('charge_already_refunded', $result->errorCode);
        self::assertSame('The payment has already been fully refunded', $result->errorMessage);
        self::assertNull($result->rawResponse);
    }

    #[Test]
    public function itCreatesFailureResultWithRawResponse(): void
    {
        $result = RefundResult::failure(
            errorCode: 'insufficient_funds',
            errorMessage: 'Not enough funds to refund',
            rawResponse: '{"error":"insufficient_funds"}',
        );

        self::assertSame('{"error":"insufficient_funds"}', $result->rawResponse);
    }

    #[Test]
    public function itSetsZeroUsdAmountOnFailure(): void
    {
        $result = RefundResult::failure('err_code', 'Some error');

        self::assertSame(0, $result->amount->getAmount());
        self::assertSame('USD', $result->amount->getCurrency()->getCurrencyCode());
    }

    // ---------------------------------------------------------------
    // isPending()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsPendingForPendingStatus(): void
    {
        $result = RefundResult::success('re_abc', 'pending', $this->makeMoney());

        self::assertTrue($result->isPending());
    }

    #[Test]
    public function itIsNotPendingForOtherStatuses(): void
    {
        foreach (['succeeded', 'failed', 'canceled'] as $status) {
            $result = RefundResult::success('re_abc', $status, $this->makeMoney());
            self::assertFalse($result->isPending(), "Status '{$status}' should not be pending");
        }
    }

    // ---------------------------------------------------------------
    // isSucceeded()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsSucceededForSucceededStatus(): void
    {
        $result = RefundResult::success('re_abc', 'succeeded', $this->makeMoney());

        self::assertTrue($result->isSucceeded());
    }

    #[Test]
    public function itIsNotSucceededForOtherStatuses(): void
    {
        foreach (['pending', 'failed', 'canceled'] as $status) {
            $result = RefundResult::success('re_abc', $status, $this->makeMoney());
            self::assertFalse($result->isSucceeded(), "Status '{$status}' should not be succeeded");
        }
    }

    // ---------------------------------------------------------------
    // isFailed()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsFailedForFailedStatus(): void
    {
        $result = RefundResult::failure('err', 'Error');

        self::assertTrue($result->isFailed());
    }

    #[Test]
    public function itIsNotFailedForSuccessStatus(): void
    {
        $result = RefundResult::success('re_abc', 'succeeded', $this->makeMoney());

        self::assertFalse($result->isFailed());
    }

    // ---------------------------------------------------------------
    // isCanceled()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsCanceledForCanceledStatus(): void
    {
        $result = RefundResult::success('re_abc', 'canceled', $this->makeMoney());

        self::assertTrue($result->isCanceled());
    }

    #[Test]
    public function itIsNotCanceledForNonCanceledStatus(): void
    {
        $result = RefundResult::success('re_abc', 'succeeded', $this->makeMoney());

        self::assertFalse($result->isCanceled());
    }

    // ---------------------------------------------------------------
    // isFinal()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsFinalForTerminalStatuses(): void
    {
        foreach (['succeeded', 'failed', 'canceled'] as $status) {
            $result = RefundResult::success('re_abc', $status, $this->makeMoney());
            self::assertTrue($result->isFinal(), "Status '{$status}' should be final");
        }
    }

    #[Test]
    public function itIsNotFinalForPendingStatus(): void
    {
        $result = RefundResult::success('re_abc', 'pending', $this->makeMoney());

        self::assertFalse($result->isFinal());
    }

    // ---------------------------------------------------------------
    // Public fields correctness
    // ---------------------------------------------------------------

    #[Test]
    public function successResultHasNullErrorFields(): void
    {
        $result = RefundResult::success('re_abc', 'succeeded', $this->makeMoney());

        self::assertNull($result->errorCode);
        self::assertNull($result->errorMessage);
    }

    #[Test]
    public function failureResultHasNullGatewayRefundId(): void
    {
        $result = RefundResult::failure('err_code', 'Msg');

        self::assertNull($result->gatewayRefundId);
    }
}
