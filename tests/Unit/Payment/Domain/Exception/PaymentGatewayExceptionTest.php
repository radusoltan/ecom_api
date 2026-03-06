<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Exception;

use App\Payment\Domain\Exception\PaymentGatewayException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentGatewayException::class)]
final class PaymentGatewayExceptionTest extends TestCase
{
    #[Test]
    public function itCreatesWithAllFields(): void
    {
        $previous = new \RuntimeException('original');
        $e = new PaymentGatewayException(
            'Payment failed',
            'card_declined',
            'Your card was declined',
            'do_not_honor',
            $previous,
        );

        self::assertSame('Payment failed', $e->getMessage());
        self::assertSame('card_declined', $e->getGatewayErrorCode());
        self::assertSame('Your card was declined', $e->getGatewayErrorMessage());
        self::assertSame('do_not_honor', $e->getDeclineCode());
        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function itCreatesWithMinimalFields(): void
    {
        $e = new PaymentGatewayException('Error', 'processing_error');

        self::assertNull($e->getGatewayErrorMessage());
        self::assertNull($e->getDeclineCode());
    }

    #[Test]
    public function itIdentifiesRetryableErrors(): void
    {
        $retryable = ['processing_error', 'gateway_timeout', 'rate_limit', 'temporary_failure'];
        foreach ($retryable as $code) {
            $e = new PaymentGatewayException('err', $code);
            self::assertTrue($e->isRetryable(), "$code should be retryable");
        }

        $notRetryable = new PaymentGatewayException('err', 'card_declined');
        self::assertFalse($notRetryable->isRetryable());
    }

    #[Test]
    public function itIdentifiesCustomerActionRequired(): void
    {
        $actionRequired = ['card_declined', 'insufficient_funds', 'invalid_card', 'expired_card', 'authentication_required'];
        foreach ($actionRequired as $code) {
            $e = new PaymentGatewayException('err', $code);
            self::assertTrue($e->requiresCustomerAction(), "$code should require customer action");
        }

        $noAction = new PaymentGatewayException('err', 'processing_error');
        self::assertFalse($noAction->requiresCustomerAction());
    }

    #[Test]
    public function itReturnsUserFriendlyMessages(): void
    {
        $codes = [
            'card_declined' => 'declined',
            'insufficient_funds' => 'Insufficient',
            'invalid_card' => 'Invalid card',
            'expired_card' => 'expired',
            'authentication_required' => 'authentication',
            'processing_error' => 'try again',
            'rate_limit' => 'Too many',
            'gateway_timeout' => 'timeout',
            'unknown_code' => 'contact support',
        ];

        foreach ($codes as $code => $expectedSubstring) {
            $e = new PaymentGatewayException('err', $code);
            self::assertStringContainsString($expectedSubstring, $e->getUserMessage(), "User message for $code should contain '$expectedSubstring'");
        }
    }
}
