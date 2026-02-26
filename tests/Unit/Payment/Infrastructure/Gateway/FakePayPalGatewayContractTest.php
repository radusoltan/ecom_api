<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Infrastructure\Gateway\FakePayPalGateway;
use Psr\Log\NullLogger;

/**
 * Runs the PaymentGatewayContractTest suite against FakePayPalGateway.
 *
 * FakePayPalGateway specifics verified by the shared contract:
 * - getGatewayId() => PaymentMethod::PAYPAL
 * - getName()      => 'PayPal Sandbox'
 * - createPaymentIntent() returns status 'requires_payment_method'
 * - gatewayPaymentIntentId is prefixed with 'PAYID-'
 * - clientSecret is prefixed with 'secret_'
 * - createRefund() gatewayRefundId is prefixed with 'REFUND-'
 *
 * Note: FakePayPalGateway uses direct constructor calls on PaymentIntentResult /
 * RefundResult (which have private constructors in production).  These calls work
 * in tests because DG\BypassFinals strips the `private` modifier from
 * src/Payment/Domain/Service/* as configured in tests/bootstrap.php.
 */
final class FakePayPalGatewayContractTest extends PaymentGatewayContractTest
{
    protected function createGateway(): PaymentGatewayInterface
    {
        return new FakePayPalGateway(new NullLogger());
    }

    protected function getExpectedGatewayId(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    protected function getExpectedName(): string
    {
        // FakePayPalGateway::getName() returns 'PayPal Sandbox'.
        return 'PayPal Sandbox';
    }
}
