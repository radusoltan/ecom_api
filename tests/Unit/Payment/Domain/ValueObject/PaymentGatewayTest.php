<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\PaymentGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentGateway::class)]
final class PaymentGatewayTest extends TestCase
{
    // ---------------------------------------------------------------
    // Named factories
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesStripeGateway(): void
    {
        $gateway = PaymentGateway::stripe();

        self::assertSame('stripe', $gateway->value());
        self::assertTrue($gateway->isStripe());
        self::assertFalse($gateway->isPaypal());
    }

    #[Test]
    public function itCreatesPaypalGateway(): void
    {
        $gateway = PaymentGateway::paypal();

        self::assertSame('paypal', $gateway->value());
        self::assertTrue($gateway->isPaypal());
        self::assertFalse($gateway->isStripe());
    }

    // ---------------------------------------------------------------
    // fromString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFromLowercaseString(): void
    {
        $stripe = PaymentGateway::fromString('stripe');
        $paypal = PaymentGateway::fromString('paypal');

        self::assertSame('stripe', $stripe->value());
        self::assertSame('paypal', $paypal->value());
    }

    #[Test]
    public function itNormalisesToLowercaseOnFromString(): void
    {
        $gateway = PaymentGateway::fromString('STRIPE');

        self::assertSame('stripe', $gateway->value());
        self::assertTrue($gateway->isStripe());
    }

    #[Test]
    public function itNormalisesPaypalUppercase(): void
    {
        $gateway = PaymentGateway::fromString('PAYPAL');

        self::assertSame('paypal', $gateway->value());
        self::assertTrue($gateway->isPaypal());
    }

    #[Test]
    public function itThrowsForUnknownGateway(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid payment gateway/');

        PaymentGateway::fromString('unknown_gateway');
    }

    #[Test]
    public function itThrowsForEmptyGateway(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentGateway::fromString('');
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsSameGateway(): void
    {
        $g1 = PaymentGateway::stripe();
        $g2 = PaymentGateway::stripe();

        self::assertTrue($g1->equals($g2));
    }

    #[Test]
    public function itDoesNotEqualDifferentGateway(): void
    {
        $stripe = PaymentGateway::stripe();
        $paypal = PaymentGateway::paypal();

        self::assertFalse($stripe->equals($paypal));
    }

    // ---------------------------------------------------------------
    // __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCastsToString(): void
    {
        self::assertSame('stripe', (string) PaymentGateway::stripe());
        self::assertSame('paypal', (string) PaymentGateway::paypal());
    }

    // ---------------------------------------------------------------
    // Exhaustive guard: only stripe/paypal accepted
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function invalidGatewayProvider(): array
    {
        return [
            'braintree' => ['braintree'],
            'adyen' => ['adyen'],
            'square' => ['square'],
            'whitespace' => ['  '],
        ];
    }

    #[Test]
    #[DataProvider('invalidGatewayProvider')]
    public function itRejectsUnsupportedGateways(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentGateway::fromString($value);
    }
}
