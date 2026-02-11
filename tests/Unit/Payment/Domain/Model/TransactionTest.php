<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Model\TransactionId;
use App\Payment\Domain\Model\TransactionStatus;
use App\Payment\Domain\Model\TransactionType;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class TransactionTest extends TestCase
{
    // =============================================
    // Authorization Transaction Tests (5 tests)
    // =============================================

    public function testItCreatesAuthorizationTransaction(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR'); // 100.00 EUR
        $gatewayTxId = 'pi_auth_123456';
        $gatewayResponse = '{"status": "succeeded", "amount": 10000}';

        // Act
        $transaction = Transaction::createAuthorization(
            $id,
            $paymentId,
            $amount,
            $gatewayTxId,
            $gatewayResponse
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertTrue($transaction->id()->equals($id));
        $this->assertTrue($transaction->paymentId()->equals($paymentId));
        $this->assertTrue($transaction->type()->isAuthorization());
        $this->assertTrue($transaction->status()->isSuccessful());
        $this->assertSame($gatewayTxId, $transaction->gatewayTransactionId());
        $this->assertSame($gatewayResponse, $transaction->gatewayResponse());
        $this->assertNull($transaction->errorCode());
        $this->assertNull($transaction->errorMessage());
    }

    public function testItCreatesAuthorizationWithoutGatewayResponse(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');
        $gatewayTxId = 'pi_auth_123456';

        // Act
        $transaction = Transaction::createAuthorization(
            $id,
            $paymentId,
            $amount,
            $gatewayTxId
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertNull($transaction->gatewayResponse());
    }

    public function testAuthorizationIsSuccessful(): void
    {
        // Arrange & Act
        $transaction = $this->createAuthorizationTransaction();

        // Assert
        $this->assertTrue($transaction->isSuccessful());
        $this->assertFalse($transaction->isFailed());
        $this->assertFalse($transaction->isPending());
    }

    public function testAuthorizationIsNotReversing(): void
    {
        // Arrange & Act
        $transaction = $this->createAuthorizationTransaction();

        // Assert
        $this->assertTrue($transaction->isAuthorization());
        $this->assertFalse($transaction->isReversing());
    }

    public function testAuthorizationHasCreatedTimestamp(): void
    {
        // Arrange
        $before = new \DateTimeImmutable();

        // Act
        $transaction = $this->createAuthorizationTransaction();
        $after = new \DateTimeImmutable();

        // Assert
        $this->assertGreaterThanOrEqual($before, $transaction->createdAt());
        $this->assertLessThanOrEqual($after, $transaction->createdAt());
    }

    // =============================================
    // Capture Transaction Tests (5 tests)
    // =============================================

    public function testItCreatesCaptureTransaction(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');
        $gatewayTxId = 'pi_capture_123456';
        $gatewayResponse = '{"status": "captured", "amount": 10000}';

        // Act
        $transaction = Transaction::createCapture(
            $id,
            $paymentId,
            $amount,
            $gatewayTxId,
            $gatewayResponse
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertTrue($transaction->type()->isCapture());
        $this->assertTrue($transaction->status()->isSuccessful());
        $this->assertSame($gatewayTxId, $transaction->gatewayTransactionId());
        $this->assertNull($transaction->errorCode());
    }

    public function testCaptureIsSuccessful(): void
    {
        // Arrange & Act
        $transaction = $this->createCaptureTransaction();

        // Assert
        $this->assertTrue($transaction->isSuccessful());
        $this->assertFalse($transaction->isFailed());
    }

    public function testCaptureIsNotReversing(): void
    {
        // Arrange & Act
        $transaction = $this->createCaptureTransaction();

        // Assert
        $this->assertTrue($transaction->isCapture());
        $this->assertFalse($transaction->isReversing());
    }

    public function testPartialCaptureWithLowerAmount(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $authorizedAmount = Money::fromScalars(10000, 'EUR'); // 100.00 EUR
        $captureAmount = Money::fromScalars(7500, 'EUR');      // 75.00 EUR (partial)
        $gatewayTxId = 'pi_partial_capture_123';

        // Act
        $transaction = Transaction::createCapture(
            $id,
            $paymentId,
            $captureAmount,
            $gatewayTxId
        );

        // Assert
        $this->assertTrue($transaction->amount()->equals($captureAmount));
        $this->assertFalse($transaction->amount()->equals($authorizedAmount));
    }

    public function testCaptureWithoutGatewayResponse(): void
    {
        // Arrange & Act
        $transaction = Transaction::createCapture(
            TransactionId::generate(),
            PaymentId::generate(),
            Money::fromScalars(10000, 'EUR'),
            'pi_capture_123'
        );

        // Assert
        $this->assertNull($transaction->gatewayResponse());
    }

    // =============================================
    // Refund Transaction Tests (5 tests)
    // =============================================

    public function testItCreatesRefundTransaction(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(5000, 'EUR'); // 50.00 EUR refund
        $gatewayTxId = 're_refund_123456';
        $gatewayResponse = '{"status": "refunded", "amount": 5000}';

        // Act
        $transaction = Transaction::createRefund(
            $id,
            $paymentId,
            $amount,
            $gatewayTxId,
            $gatewayResponse
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertTrue($transaction->type()->isRefund());
        $this->assertTrue($transaction->status()->isSuccessful());
        $this->assertSame($gatewayTxId, $transaction->gatewayTransactionId());
    }

    public function testRefundIsSuccessful(): void
    {
        // Arrange & Act
        $transaction = $this->createRefundTransaction();

        // Assert
        $this->assertTrue($transaction->isSuccessful());
        $this->assertFalse($transaction->isFailed());
    }

    public function testRefundIsReversing(): void
    {
        // Arrange & Act
        $transaction = $this->createRefundTransaction();

        // Assert
        $this->assertTrue($transaction->isRefund());
        $this->assertTrue($transaction->isReversing());
    }

    public function testPartialRefund(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $capturedAmount = Money::fromScalars(10000, 'EUR'); // 100.00 EUR
        $refundAmount = Money::fromScalars(3000, 'EUR');     // 30.00 EUR (partial)
        $gatewayTxId = 're_partial_123';

        // Act
        $transaction = Transaction::createRefund(
            $id,
            $paymentId,
            $refundAmount,
            $gatewayTxId
        );

        // Assert
        $this->assertTrue($transaction->amount()->equals($refundAmount));
        $this->assertFalse($transaction->amount()->equals($capturedAmount));
    }

    public function testRefundWithoutGatewayResponse(): void
    {
        // Arrange & Act
        $transaction = $this->createRefundTransaction(null);

        // Assert
        $this->assertNull($transaction->gatewayResponse());
    }

    // =============================================
    // Void Transaction Tests (5 tests)
    // =============================================

    public function testItCreatesVoidTransaction(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');
        $gatewayTxId = 'pi_void_123456';
        $gatewayResponse = '{"status": "voided"}';

        // Act
        $transaction = Transaction::createVoid(
            $id,
            $paymentId,
            $amount,
            $gatewayTxId,
            $gatewayResponse
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertTrue($transaction->type()->isVoid());
        $this->assertTrue($transaction->status()->isSuccessful());
        $this->assertSame($gatewayTxId, $transaction->gatewayTransactionId());
    }

    public function testVoidIsSuccessful(): void
    {
        // Arrange & Act
        $transaction = $this->createVoidTransaction();

        // Assert
        $this->assertTrue($transaction->isSuccessful());
        $this->assertFalse($transaction->isFailed());
    }

    public function testVoidIsNotReversing(): void
    {
        // Arrange & Act
        $transaction = $this->createVoidTransaction();

        // Assert - VOID is not reversing because it cancels authorization, not capture
        // It doesn't return money to customer, just releases the hold
        $this->assertTrue($transaction->isVoid());
        $this->assertFalse($transaction->isReversing());
    }

    public function testVoidCancelsAuthorization(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $authorizedAmount = Money::fromScalars(10000, 'EUR');

        // Create authorization
        $authTx = Transaction::createAuthorization(
            TransactionId::generate(),
            $paymentId,
            $authorizedAmount,
            'pi_auth_123'
        );

        // Act - void the authorization
        $voidTx = Transaction::createVoid(
            TransactionId::generate(),
            $paymentId,
            $authorizedAmount,
            'pi_void_123'
        );

        // Assert
        $this->assertTrue($authTx->isAuthorization());
        $this->assertTrue($voidTx->isVoid());
        $this->assertTrue($voidTx->amount()->equals($authorizedAmount));
    }

    public function testVoidWithoutGatewayResponse(): void
    {
        // Arrange & Act
        $transaction = $this->createVoidTransaction(null);

        // Assert
        $this->assertNull($transaction->gatewayResponse());
    }

    // =============================================
    // Failed Transaction Tests (8 tests)
    // =============================================

    public function testItCreatesFailedAuthorizationTransaction(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');
        $errorCode = 'card_declined';
        $errorMessage = 'Your card was declined';
        $gatewayResponse = '{"error": {"code": "card_declined"}}';

        // Act
        $transaction = Transaction::createFailed(
            $id,
            $paymentId,
            TransactionType::AUTHORIZATION,
            $amount,
            $errorCode,
            $errorMessage,
            $gatewayResponse
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertTrue($transaction->type()->isAuthorization());
        $this->assertTrue($transaction->status()->isFailed());
        $this->assertSame('', $transaction->gatewayTransactionId()); // Empty for failed
        $this->assertSame($errorCode, $transaction->errorCode());
        $this->assertSame($errorMessage, $transaction->errorMessage());
        $this->assertSame($gatewayResponse, $transaction->gatewayResponse());
    }

    public function testFailedTransactionIsFailed(): void
    {
        // Arrange & Act
        $transaction = $this->createFailedTransaction();

        // Assert
        $this->assertTrue($transaction->isFailed());
        $this->assertFalse($transaction->isSuccessful());
        $this->assertFalse($transaction->isPending());
    }

    public function testFailedTransactionHasErrorInformation(): void
    {
        // Arrange & Act
        $transaction = Transaction::createFailed(
            TransactionId::generate(),
            PaymentId::generate(),
            TransactionType::CAPTURE,
            Money::fromScalars(10000, 'EUR'),
            'insufficient_funds',
            'Insufficient funds',
            null
        );

        // Assert
        $this->assertSame('insufficient_funds', $transaction->errorCode());
        $this->assertSame('Insufficient funds', $transaction->errorMessage());
    }

    public function testFailedCaptureTransaction(): void
    {
        // Arrange & Act
        $transaction = Transaction::createFailed(
            TransactionId::generate(),
            PaymentId::generate(),
            TransactionType::CAPTURE,
            Money::fromScalars(10000, 'EUR'),
            'processing_error',
            'Payment processing error'
        );

        // Assert
        $this->assertTrue($transaction->type()->isCapture());
        $this->assertTrue($transaction->isFailed());
    }

    public function testFailedRefundTransaction(): void
    {
        // Arrange & Act
        $transaction = Transaction::createFailed(
            TransactionId::generate(),
            PaymentId::generate(),
            TransactionType::REFUND,
            Money::fromScalars(5000, 'EUR'),
            'refund_failed',
            'Refund processing failed'
        );

        // Assert
        $this->assertTrue($transaction->type()->isRefund());
        $this->assertTrue($transaction->isFailed());
        $this->assertTrue($transaction->isReversing()); // Refund is still a reversing type
    }

    public function testFailedTransactionWithoutGatewayResponse(): void
    {
        // Arrange & Act
        $transaction = Transaction::createFailed(
            TransactionId::generate(),
            PaymentId::generate(),
            TransactionType::AUTHORIZATION,
            Money::fromScalars(10000, 'EUR'),
            'card_declined',
            'Card declined'
        );

        // Assert
        $this->assertNull($transaction->gatewayResponse());
    }

    public function testFailedTransactionHasEmptyGatewayTransactionId(): void
    {
        // Arrange & Act
        $transaction = $this->createFailedTransaction();

        // Assert
        $this->assertSame('', $transaction->gatewayTransactionId());
    }

    public function testFailedTransactionCreatedTimestamp(): void
    {
        // Arrange
        $before = new \DateTimeImmutable();

        // Act
        $transaction = $this->createFailedTransaction();
        $after = new \DateTimeImmutable();

        // Assert
        $this->assertGreaterThanOrEqual($before, $transaction->createdAt());
        $this->assertLessThanOrEqual($after, $transaction->createdAt());
    }

    // =============================================
    // Reconstitution Tests (3 tests)
    // =============================================

    public function testItReconstitutesFromPersistence(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');
        $gatewayTxId = 'pi_reconstituted_123';
        $gatewayResponse = '{"status": "succeeded"}';
        $createdAt = new \DateTimeImmutable('2025-01-15 10:00:00');

        // Act
        $transaction = Transaction::reconstituteFromPersistence(
            $id,
            $paymentId,
            TransactionType::CAPTURE,
            $amount,
            $gatewayTxId,
            $gatewayResponse,
            TransactionStatus::SUCCESS,
            null,
            null,
            $createdAt
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertTrue($transaction->id()->equals($id));
        $this->assertTrue($transaction->paymentId()->equals($paymentId));
        $this->assertTrue($transaction->type()->isCapture());
        $this->assertTrue($transaction->status()->isSuccessful());
        $this->assertSame($gatewayTxId, $transaction->gatewayTransactionId());
        $this->assertSame($gatewayResponse, $transaction->gatewayResponse());
        $this->assertSame($createdAt, $transaction->createdAt());
    }

    public function testItReconstitutesFailedTransaction(): void
    {
        // Arrange
        $id = TransactionId::generate();
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');
        $errorCode = 'card_declined';
        $errorMessage = 'Card was declined';
        $createdAt = new \DateTimeImmutable('2025-01-15 10:00:00');

        // Act
        $transaction = Transaction::reconstituteFromPersistence(
            $id,
            $paymentId,
            TransactionType::AUTHORIZATION,
            $amount,
            '',
            null,
            TransactionStatus::FAILED,
            $errorCode,
            $errorMessage,
            $createdAt
        );

        // Assert
        $this->assertTrue($transaction->isFailed());
        $this->assertSame($errorCode, $transaction->errorCode());
        $this->assertSame($errorMessage, $transaction->errorMessage());
        $this->assertSame('', $transaction->gatewayTransactionId());
    }

    public function testReconstitutedTransactionPreservesTimestamp(): void
    {
        // Arrange
        $specificTime = new \DateTimeImmutable('2024-12-01 15:30:00');

        // Act
        $transaction = Transaction::reconstituteFromPersistence(
            TransactionId::generate(),
            PaymentId::generate(),
            TransactionType::CAPTURE,
            Money::fromScalars(10000, 'EUR'),
            'pi_123',
            null,
            TransactionStatus::SUCCESS,
            null,
            null,
            $specificTime
        );

        // Assert
        $this->assertSame($specificTime, $transaction->createdAt());
    }

    // =============================================
    // Immutability Tests (3 tests)
    // =============================================

    public function testTransactionIsReadonly(): void
    {
        // Arrange & Act
        $transaction = $this->createAuthorizationTransaction();

        // Assert - Transaction class is readonly, so all properties are immutable
        $reflection = new \ReflectionClass($transaction);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testTransactionAmountIsImmutable(): void
    {
        // Arrange
        $amount = Money::fromScalars(10000, 'EUR');
        $transaction = Transaction::createAuthorization(
            TransactionId::generate(),
            PaymentId::generate(),
            $amount,
            'pi_123'
        );

        // Act - Money is also immutable, so creating new amount doesn't affect transaction
        $newAmount = Money::fromScalars(20000, 'EUR');

        // Assert
        $this->assertTrue($transaction->amount()->equals($amount));
        $this->assertFalse($transaction->amount()->equals($newAmount));
    }

    public function testTransactionCreatedAtIsImmutable(): void
    {
        // Arrange & Act
        $transaction = $this->createAuthorizationTransaction();
        $originalCreatedAt = $transaction->createdAt();

        // Wait to ensure time difference
        usleep(1000); // 1ms

        // Assert - createdAt should not change
        $this->assertSame($originalCreatedAt, $transaction->createdAt());
    }

    // =============================================
    // Query Method Tests (5 tests)
    // =============================================

    public function testTypeQueryMethods(): void
    {
        // Authorization
        $authTx = $this->createAuthorizationTransaction();
        $this->assertTrue($authTx->isAuthorization());
        $this->assertFalse($authTx->isCapture());
        $this->assertFalse($authTx->isRefund());
        $this->assertFalse($authTx->isVoid());

        // Capture
        $captureTx = $this->createCaptureTransaction();
        $this->assertTrue($captureTx->isCapture());
        $this->assertFalse($captureTx->isAuthorization());

        // Refund
        $refundTx = $this->createRefundTransaction();
        $this->assertTrue($refundTx->isRefund());
        $this->assertFalse($refundTx->isCapture());

        // Void
        $voidTx = $this->createVoidTransaction();
        $this->assertTrue($voidTx->isVoid());
        $this->assertFalse($voidTx->isRefund());
    }

    public function testStatusQueryMethods(): void
    {
        // Successful
        $successTx = $this->createAuthorizationTransaction();
        $this->assertTrue($successTx->isSuccessful());
        $this->assertFalse($successTx->isFailed());
        $this->assertFalse($successTx->isPending());

        // Failed
        $failedTx = $this->createFailedTransaction();
        $this->assertTrue($failedTx->isFailed());
        $this->assertFalse($failedTx->isSuccessful());
    }

    public function testReversingQueryMethod(): void
    {
        // Non-reversing types: Authorization, Capture, Void
        // VOID is not reversing because it cancels authorization (no money was charged)
        $authTx = $this->createAuthorizationTransaction();
        $captureTx = $this->createCaptureTransaction();
        $voidTx = $this->createVoidTransaction();
        $this->assertFalse($authTx->isReversing());
        $this->assertFalse($captureTx->isReversing());
        $this->assertFalse($voidTx->isReversing());

        // Reversing types: Refund returns money that was captured
        $refundTx = $this->createRefundTransaction();
        $this->assertTrue($refundTx->isReversing());
    }

    public function testAllTransactionTypesHaveAmounts(): void
    {
        // Arrange
        $amount = Money::fromScalars(10000, 'EUR');

        // Act
        $authTx = Transaction::createAuthorization(
            TransactionId::generate(),
            PaymentId::generate(),
            $amount,
            'pi_auth'
        );

        $captureTx = Transaction::createCapture(
            TransactionId::generate(),
            PaymentId::generate(),
            $amount,
            'pi_capture'
        );

        $refundTx = Transaction::createRefund(
            TransactionId::generate(),
            PaymentId::generate(),
            $amount,
            're_refund'
        );

        $voidTx = Transaction::createVoid(
            TransactionId::generate(),
            PaymentId::generate(),
            $amount,
            'pi_void'
        );

        // Assert
        $this->assertTrue($authTx->amount()->equals($amount));
        $this->assertTrue($captureTx->amount()->equals($amount));
        $this->assertTrue($refundTx->amount()->equals($amount));
        $this->assertTrue($voidTx->amount()->equals($amount));
    }

    public function testTransactionLinksToPayment(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();

        // Act
        $tx1 = Transaction::createAuthorization(
            TransactionId::generate(),
            $paymentId,
            Money::fromScalars(10000, 'EUR'),
            'pi_auth'
        );

        $tx2 = Transaction::createCapture(
            TransactionId::generate(),
            $paymentId,
            Money::fromScalars(10000, 'EUR'),
            'pi_capture'
        );

        // Assert - both transactions link to same payment
        $this->assertTrue($tx1->paymentId()->equals($paymentId));
        $this->assertTrue($tx2->paymentId()->equals($paymentId));
        $this->assertTrue($tx1->paymentId()->equals($tx2->paymentId()));
    }

    // =============================================
    // Helper Methods
    // =============================================

    private function createAuthorizationTransaction(): Transaction
    {
        return Transaction::createAuthorization(
            TransactionId::generate(),
            PaymentId::generate(),
            Money::fromScalars(10000, 'EUR'),
            'pi_auth_123456',
            '{"status": "succeeded"}'
        );
    }

    private function createCaptureTransaction(): Transaction
    {
        return Transaction::createCapture(
            TransactionId::generate(),
            PaymentId::generate(),
            Money::fromScalars(10000, 'EUR'),
            'pi_capture_123456',
            '{"status": "captured"}'
        );
    }

    private function createRefundTransaction(?string $gatewayResponse = '{"status": "refunded"}'): Transaction
    {
        return Transaction::createRefund(
            TransactionId::generate(),
            PaymentId::generate(),
            Money::fromScalars(5000, 'EUR'),
            're_refund_123456',
            $gatewayResponse
        );
    }

    private function createVoidTransaction(?string $gatewayResponse = '{"status": "voided"}'): Transaction
    {
        return Transaction::createVoid(
            TransactionId::generate(),
            PaymentId::generate(),
            Money::fromScalars(10000, 'EUR'),
            'pi_void_123456',
            $gatewayResponse
        );
    }

    private function createFailedTransaction(): Transaction
    {
        return Transaction::createFailed(
            TransactionId::generate(),
            PaymentId::generate(),
            TransactionType::AUTHORIZATION,
            Money::fromScalars(10000, 'EUR'),
            'card_declined',
            'Your card was declined',
            '{"error": {"code": "card_declined"}}'
        );
    }
}
