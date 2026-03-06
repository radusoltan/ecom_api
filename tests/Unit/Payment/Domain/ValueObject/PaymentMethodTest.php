<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\PaymentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentMethod::class)]
final class PaymentMethodTest extends TestCase
{
    // ---------------------------------------------------------------
    // Named factories
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesCardMethod(): void
    {
        $method = PaymentMethod::card();

        self::assertSame('card', $method->value());
        self::assertTrue($method->isCard());
        self::assertFalse($method->isPaypal());
        self::assertFalse($method->isBankTransfer());
        self::assertFalse($method->isApplePay());
        self::assertFalse($method->isGooglePay());
    }

    #[Test]
    public function itCreatesPaypalMethod(): void
    {
        $method = PaymentMethod::paypal();

        self::assertSame('paypal', $method->value());
        self::assertTrue($method->isPaypal());
        self::assertFalse($method->isCard());
    }

    #[Test]
    public function itCreatesBankTransferMethod(): void
    {
        $method = PaymentMethod::bankTransfer();

        self::assertSame('bank_transfer', $method->value());
        self::assertTrue($method->isBankTransfer());
        self::assertFalse($method->isCard());
    }

    #[Test]
    public function itCreatesApplePayMethod(): void
    {
        $method = PaymentMethod::applePay();

        self::assertSame('apple_pay', $method->value());
        self::assertTrue($method->isApplePay());
        self::assertFalse($method->isGooglePay());
    }

    #[Test]
    public function itCreatesGooglePayMethod(): void
    {
        $method = PaymentMethod::googlePay();

        self::assertSame('google_pay', $method->value());
        self::assertTrue($method->isGooglePay());
        self::assertFalse($method->isApplePay());
    }

    // ---------------------------------------------------------------
    // fromString()
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function validMethodProvider(): array
    {
        return [
            'card lowercase' => ['card', 'card'],
            'card uppercase' => ['CARD', 'card'],
            'paypal lowercase' => ['paypal', 'paypal'],
            'bank_transfer lowercase' => ['bank_transfer', 'bank_transfer'],
            'apple_pay lowercase' => ['apple_pay', 'apple_pay'],
            'google_pay uppercase' => ['GOOGLE_PAY', 'google_pay'],
        ];
    }

    #[Test]
    #[DataProvider('validMethodProvider')]
    public function itCreatesFromStringWithNormalisation(string $input, string $expected): void
    {
        $method = PaymentMethod::fromString($input);

        self::assertSame($expected, $method->value());
    }

    #[Test]
    public function itThrowsForUnknownMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid payment method/');

        PaymentMethod::fromString('cash');
    }

    #[Test]
    public function itThrowsForEmptyMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentMethod::fromString('');
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsSameMethod(): void
    {
        $m1 = PaymentMethod::card();
        $m2 = PaymentMethod::card();

        self::assertTrue($m1->equals($m2));
    }

    #[Test]
    public function itDoesNotEqualDifferentMethod(): void
    {
        $card = PaymentMethod::card();
        $paypal = PaymentMethod::paypal();

        self::assertFalse($card->equals($paypal));
    }

    // ---------------------------------------------------------------
    // __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCastsToString(): void
    {
        self::assertSame('card', (string) PaymentMethod::card());
        self::assertSame('bank_transfer', (string) PaymentMethod::bankTransfer());
    }

    // ---------------------------------------------------------------
    // Invalid method guard
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function invalidMethodProvider(): array
    {
        return [
            'crypto' => ['crypto'],
            'wire' => ['wire'],
            'check' => ['check'],
            'whitespace' => ['   '],
        ];
    }

    #[Test]
    #[DataProvider('invalidMethodProvider')]
    public function itRejectsUnsupportedMethods(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentMethod::fromString($value);
    }
}
