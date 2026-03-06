<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Exception;

use App\Payment\Domain\Exception\CardDeclinedException;
use App\Payment\Domain\Exception\InsufficientFundsException;
use App\Payment\Domain\Exception\InvalidPaymentMethodException;
use App\Payment\Domain\Exception\PaymentGatewayException;
use App\Payment\Domain\Exception\UnsupportedPaymentMethodException;
use App\Payment\Domain\Model\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CardDeclinedException::class)]
#[CoversClass(InsufficientFundsException::class)]
#[CoversClass(InvalidPaymentMethodException::class)]
#[CoversClass(UnsupportedPaymentMethodException::class)]
final class PaymentExceptionsExtendedTest extends TestCase
{
    // --- CardDeclinedException ---

    #[Test]
    public function cardDeclinedWithInsufficientFunds(): void
    {
        $e = CardDeclinedException::create('insufficient_funds', 'Not enough money');

        self::assertInstanceOf(PaymentGatewayException::class, $e);
        self::assertStringContainsString('Insufficient funds', $e->getMessage());
        self::assertSame('card_declined', $e->getGatewayErrorCode());
        self::assertSame('insufficient_funds', $e->getDeclineCode());
        self::assertSame('Not enough money', $e->getGatewayErrorMessage());
        self::assertTrue($e->isInsufficientFunds());
        self::assertFalse($e->isFraudulent());
        self::assertTrue($e->requiresDifferentPaymentMethod());
    }

    #[Test]
    public function cardDeclinedWithFraud(): void
    {
        $e = CardDeclinedException::create('fraudulent');

        self::assertTrue($e->isFraudulent());
        self::assertFalse($e->isInsufficientFunds());
        self::assertTrue($e->requiresDifferentPaymentMethod());
        self::assertFalse($e->canRetryWithSameCard());
    }

    #[Test]
    public function cardDeclinedLostOrStolen(): void
    {
        $lost = CardDeclinedException::create('lost_card');
        $stolen = CardDeclinedException::create('stolen_card');

        self::assertTrue($lost->isLostOrStolen());
        self::assertTrue($stolen->isLostOrStolen());
        self::assertTrue($lost->requiresDifferentPaymentMethod());
    }

    #[Test]
    public function cardDeclinedExpired(): void
    {
        $e = CardDeclinedException::create('expired_card');

        self::assertTrue($e->isExpired());
        self::assertTrue($e->requiresDifferentPaymentMethod());
        self::assertFalse($e->canRetryWithSameCard());
    }

    #[Test]
    public function cardDeclinedRetryable(): void
    {
        $retryable = ['processing_error', 'card_velocity_exceeded', 'do_not_honor'];

        foreach ($retryable as $code) {
            $e = CardDeclinedException::create($code);
            self::assertTrue($e->canRetryWithSameCard(), "$code should be retryable");
            self::assertFalse($e->requiresDifferentPaymentMethod(), "$code should not require different method");
        }
    }

    #[Test]
    public function cardDeclinedGenericCode(): void
    {
        $e = CardDeclinedException::create('generic_decline');

        self::assertStringContainsString('generic_decline', $e->getMessage());
        self::assertFalse($e->isInsufficientFunds());
        self::assertFalse($e->isFraudulent());
        self::assertFalse($e->isLostOrStolen());
        self::assertFalse($e->isExpired());
    }

    #[Test]
    public function cardDeclinedWithPreviousException(): void
    {
        $prev = new \RuntimeException('stripe error');
        $e = CardDeclinedException::create('do_not_honor', 'Gateway down', $prev);

        self::assertSame($prev, $e->getPrevious());
    }

    #[Test]
    public function cardDeclinedAllDeclineCodes(): void
    {
        $codes = [
            'insufficient_funds', 'lost_card', 'stolen_card', 'expired_card',
            'incorrect_cvc', 'fraudulent', 'do_not_honor', 'card_velocity_exceeded',
            'withdrawal_count_limit_exceeded', 'invalid_account', 'new_account_information_available',
        ];

        foreach ($codes as $code) {
            $e = CardDeclinedException::create($code);
            self::assertNotEmpty($e->getMessage(), "Message for $code should not be empty");
        }
    }

    // --- InsufficientFundsException ---

    #[Test]
    public function insufficientFundsWithAmount(): void
    {
        $amount = Money::of('99.99', 'USD');
        $e = InsufficientFundsException::create($amount, 'Not enough balance');

        self::assertInstanceOf(PaymentGatewayException::class, $e);
        self::assertSame('insufficient_funds', $e->getGatewayErrorCode());
        self::assertSame('insufficient_funds', $e->getDeclineCode());
        self::assertSame($amount, $e->getAttemptedAmount());
        self::assertFalse($e->isRetryable());
        self::assertTrue($e->requiresCustomerAction());
        self::assertStringContainsString('99.99', $e->getMessage());
    }

    #[Test]
    public function insufficientFundsWithoutAmount(): void
    {
        $e = InsufficientFundsException::create();

        self::assertNull($e->getAttemptedAmount());
        self::assertStringContainsString('Insufficient funds', $e->getMessage());
        self::assertStringContainsString('insufficient funds', $e->getUserMessage());
    }

    #[Test]
    public function insufficientFundsUserMessageWithAmount(): void
    {
        $amount = Money::of('50.00', 'EUR');
        $e = InsufficientFundsException::create($amount);

        self::assertStringContainsString('different payment method', $e->getUserMessage());
    }

    // --- InvalidPaymentMethodException ---

    #[Test]
    public function invalidCard(): void
    {
        $e = InvalidPaymentMethodException::invalidCard('Card number invalid', 'card_number');

        self::assertSame('invalid_card', $e->getGatewayErrorCode());
        self::assertSame('card_number', $e->getField());
        self::assertStringContainsString('Card number invalid', $e->getMessage());
        self::assertFalse($e->isRetryable());
        self::assertTrue($e->requiresCustomerAction());
    }

    #[Test]
    public function invalidCardNoReason(): void
    {
        $e = InvalidPaymentMethodException::invalidCard();

        self::assertSame('Invalid payment method', $e->getMessage());
        self::assertNull($e->getField());
    }

    #[Test]
    public function invalidCardNumber(): void
    {
        $e = InvalidPaymentMethodException::invalidCardNumber('Bad number');

        self::assertSame('invalid_card_number', $e->getGatewayErrorCode());
        self::assertSame('card_number', $e->getField());
        self::assertStringContainsString('card number', $e->getUserMessage());
    }

    #[Test]
    public function expiredCard(): void
    {
        $e = InvalidPaymentMethodException::expiredCard();

        self::assertSame('expired_card', $e->getGatewayErrorCode());
        self::assertSame('expiry_date', $e->getField());
        self::assertStringContainsString('expired', $e->getUserMessage());
    }

    #[Test]
    public function invalidExpiryDate(): void
    {
        $e = InvalidPaymentMethodException::invalidExpiryDate();

        self::assertSame('invalid_expiry_date', $e->getGatewayErrorCode());
        self::assertSame('expiry_date', $e->getField());
    }

    #[Test]
    public function invalidCvc(): void
    {
        $e = InvalidPaymentMethodException::invalidCvc();

        self::assertSame('invalid_cvc', $e->getGatewayErrorCode());
        self::assertSame('cvc', $e->getField());
        self::assertStringContainsString('security code', $e->getUserMessage());
    }

    #[Test]
    public function cardNotSupported(): void
    {
        $e = InvalidPaymentMethodException::cardNotSupported('Amex');

        self::assertSame('card_not_supported', $e->getGatewayErrorCode());
        self::assertStringContainsString('Amex', $e->getMessage());
    }

    #[Test]
    public function cardNotSupportedNoType(): void
    {
        $e = InvalidPaymentMethodException::cardNotSupported();

        self::assertSame('Card type not supported', $e->getMessage());
    }

    #[Test]
    public function paymentMethodNotFound(): void
    {
        $e = InvalidPaymentMethodException::paymentMethodNotFound('pm_123');

        self::assertSame('payment_method_not_found', $e->getGatewayErrorCode());
        self::assertNull($e->getField());
        self::assertStringContainsString('pm_123', $e->getMessage());
    }

    #[Test]
    public function invalidPaymentMethodUserMessages(): void
    {
        $cases = [
            'invalid_card_number' => InvalidPaymentMethodException::invalidCardNumber(),
            'expired_card' => InvalidPaymentMethodException::expiredCard(),
            'invalid_expiry_date' => InvalidPaymentMethodException::invalidExpiryDate(),
            'invalid_cvc' => InvalidPaymentMethodException::invalidCvc(),
            'card_not_supported' => InvalidPaymentMethodException::cardNotSupported(),
            'payment_method_not_found' => InvalidPaymentMethodException::paymentMethodNotFound('x'),
            'invalid_card' => InvalidPaymentMethodException::invalidCard(),
        ];

        foreach ($cases as $code => $e) {
            self::assertNotEmpty($e->getUserMessage(), "User message for $code should not be empty");
        }
    }

    // --- UnsupportedPaymentMethodException ---

    #[Test]
    public function unsupportedPaymentMethod(): void
    {
        $method = PaymentMethod::BANK_TRANSFER;
        $e = new UnsupportedPaymentMethodException($method);

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame($method, $e->getPaymentMethod());
        self::assertStringContainsString('bank_transfer', $e->getMessage());
        self::assertNotEmpty($e->getUserMessage());
    }

    #[Test]
    public function unsupportedPaymentMethodWithPrevious(): void
    {
        $prev = new \RuntimeException('original');
        $e = new UnsupportedPaymentMethodException(PaymentMethod::BANK_TRANSFER, $prev);

        self::assertSame($prev, $e->getPrevious());
    }
}
