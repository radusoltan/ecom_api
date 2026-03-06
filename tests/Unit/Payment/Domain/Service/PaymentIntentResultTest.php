<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Service;

use App\Payment\Domain\Service\PaymentIntentResult;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentIntentResult::class)]
final class PaymentIntentResultTest extends TestCase
{
    private function makeMoney(int $cents = 9999, string $currency = 'USD'): Money
    {
        return Money::fromScalars($cents, $currency);
    }

    // ---------------------------------------------------------------
    // success()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesSuccessResult(): void
    {
        $amount = $this->makeMoney(9999, 'USD');

        $result = PaymentIntentResult::success(
            gatewayPaymentIntentId: 'pi_abc123',
            status: 'requires_capture',
            amount: $amount,
        );

        self::assertTrue($result->success);
        self::assertSame('pi_abc123', $result->gatewayPaymentIntentId);
        self::assertSame('requires_capture', $result->status);
        self::assertSame($amount, $result->amount);
        self::assertNull($result->clientSecret);
        self::assertNull($result->customerId);
        self::assertNull($result->errorCode);
        self::assertNull($result->errorMessage);
        self::assertNull($result->rawResponse);
    }

    #[Test]
    public function itCreatesSuccessResultWithAllOptionalFields(): void
    {
        $amount = $this->makeMoney(5000, 'EUR');

        $result = PaymentIntentResult::success(
            gatewayPaymentIntentId: 'pi_xyz789',
            status: 'succeeded',
            amount: $amount,
            clientSecret: 'pi_xyz789_secret_abc',
            customerId: 'cus_customer123',
            rawResponse: '{"id":"pi_xyz789"}',
        );

        self::assertSame('pi_xyz789_secret_abc', $result->clientSecret);
        self::assertSame('cus_customer123', $result->customerId);
        self::assertSame('{"id":"pi_xyz789"}', $result->rawResponse);
    }

    // ---------------------------------------------------------------
    // failure()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFailureResult(): void
    {
        $result = PaymentIntentResult::failure(
            errorCode: 'card_declined',
            errorMessage: 'Your card was declined',
        );

        self::assertFalse($result->success);
        self::assertSame('failed', $result->status);
        self::assertSame('card_declined', $result->errorCode);
        self::assertSame('Your card was declined', $result->errorMessage);
        self::assertSame('', $result->gatewayPaymentIntentId);
        self::assertNull($result->clientSecret);
        self::assertNull($result->customerId);
        self::assertNull($result->rawResponse);
    }

    #[Test]
    public function itCreatesFailureResultWithOptionalGatewayId(): void
    {
        $result = PaymentIntentResult::failure(
            errorCode: 'insufficient_funds',
            errorMessage: 'Not enough funds',
            gatewayPaymentIntentId: 'pi_failed_abc',
            rawResponse: '{"error":"insufficient_funds"}',
        );

        self::assertSame('pi_failed_abc', $result->gatewayPaymentIntentId);
        self::assertSame('{"error":"insufficient_funds"}', $result->rawResponse);
    }

    #[Test]
    public function itSetsZeroAmountOnFailure(): void
    {
        $result = PaymentIntentResult::failure('card_declined', 'Declined');

        self::assertSame(0, $result->amount->getAmount());
        self::assertSame('USD', $result->amount->getCurrency()->getCurrencyCode());
    }

    // ---------------------------------------------------------------
    // requiresAction()
    // ---------------------------------------------------------------

    #[Test]
    public function itRequiresActionForRequiresActionStatus(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'requires_action', $this->makeMoney());

        self::assertTrue($result->requiresAction());
    }

    #[Test]
    public function itDoesNotRequireActionForOtherStatuses(): void
    {
        foreach (['succeeded', 'requires_capture', 'processing', 'failed', 'canceled'] as $status) {
            $result = PaymentIntentResult::success('pi_abc', $status, $this->makeMoney());
            self::assertFalse($result->requiresAction(), "Status '{$status}' should not require action");
        }
    }

    // ---------------------------------------------------------------
    // isAuthorized()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsAuthorizedForRequiresCaptureStatus(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'requires_capture', $this->makeMoney());

        self::assertTrue($result->isAuthorized());
    }

    #[Test]
    public function itIsAuthorizedForProcessingStatus(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'processing', $this->makeMoney());

        self::assertTrue($result->isAuthorized());
    }

    #[Test]
    public function itIsNotAuthorizedForOtherStatuses(): void
    {
        foreach (['succeeded', 'requires_action', 'canceled', 'failed'] as $status) {
            $result = PaymentIntentResult::success('pi_abc', $status, $this->makeMoney());
            self::assertFalse($result->isAuthorized(), "Status '{$status}' should not be authorized");
        }
    }

    // ---------------------------------------------------------------
    // isCaptured()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsCapturedForSucceededStatus(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'succeeded', $this->makeMoney());

        self::assertTrue($result->isCaptured());
    }

    #[Test]
    public function itIsNotCapturedForNonSucceededStatuses(): void
    {
        foreach (['requires_capture', 'processing', 'requires_action', 'failed'] as $status) {
            $result = PaymentIntentResult::success('pi_abc', $status, $this->makeMoney());
            self::assertFalse($result->isCaptured(), "Status '{$status}' should not be captured");
        }
    }

    // ---------------------------------------------------------------
    // isFinal()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsFinalForTerminalStatuses(): void
    {
        foreach (['succeeded', 'canceled', 'failed'] as $status) {
            $result = PaymentIntentResult::success('pi_abc', $status, $this->makeMoney());
            self::assertTrue($result->isFinal(), "Status '{$status}' should be final");
        }
    }

    #[Test]
    public function itIsNotFinalForNonTerminalStatuses(): void
    {
        foreach (['requires_capture', 'processing', 'requires_action', 'requires_confirmation'] as $status) {
            $result = PaymentIntentResult::success('pi_abc', $status, $this->makeMoney());
            self::assertFalse($result->isFinal(), "Status '{$status}' should not be final");
        }
    }

    // ---------------------------------------------------------------
    // requiresConfirmation()
    // ---------------------------------------------------------------

    #[Test]
    public function itRequiresConfirmationForRequiresConfirmationStatus(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'requires_confirmation', $this->makeMoney());

        self::assertTrue($result->requiresConfirmation());
    }

    #[Test]
    public function itDoesNotRequireConfirmationForOtherStatuses(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'requires_capture', $this->makeMoney());

        self::assertFalse($result->requiresConfirmation());
    }

    // ---------------------------------------------------------------
    // isProcessing()
    // ---------------------------------------------------------------

    #[Test]
    public function itIsProcessingForProcessingStatus(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'processing', $this->makeMoney());

        self::assertTrue($result->isProcessing());
    }

    #[Test]
    public function itIsNotProcessingForNonProcessingStatus(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'succeeded', $this->makeMoney());

        self::assertFalse($result->isProcessing());
    }

    // ---------------------------------------------------------------
    // Success vs failure flag
    // ---------------------------------------------------------------

    #[Test]
    public function successResultIsNotFailure(): void
    {
        $result = PaymentIntentResult::success('pi_abc', 'succeeded', $this->makeMoney());

        self::assertTrue($result->success);
    }

    #[Test]
    public function failureResultIsNotSuccess(): void
    {
        $result = PaymentIntentResult::failure('err', 'Something failed');

        self::assertFalse($result->success);
    }
}
