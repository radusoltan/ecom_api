<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Infrastructure\Gateway\FakeStripeGateway;
use Psr\Log\NullLogger;

/**
 * Runs the PaymentGatewayContractTest suite against FakeStripeGateway.
 *
 * FakeStripeGateway specifics verified by the shared contract:
 * - getGatewayId() => PaymentMethod::STRIPE
 * - getName()      => 'Stripe Sandbox'
 * - createPaymentIntent() without require3DS returns status 'requires_payment_method'
 * - gatewayPaymentIntentId is prefixed with 'pi_fake_'
 * - clientSecret is prefixed with 'secret_fake_'
 * - createRefund() gatewayRefundId is prefixed with 're_fake_'
 */
final class FakeStripeGatewayContractTest extends PaymentGatewayContractTestCase
{
    protected function createGateway(): PaymentGatewayInterface
    {
        return new FakeStripeGateway(new NullLogger());
    }

    protected function getExpectedGatewayId(): PaymentMethod
    {
        return PaymentMethod::STRIPE;
    }

    protected function getExpectedName(): string
    {
        // FakeStripeGateway::getName() returns 'Stripe Sandbox'.
        return 'Stripe Sandbox';
    }
}
