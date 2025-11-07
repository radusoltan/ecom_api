<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;

/**
 * Payment Gateway Factory.
 *
 * Factory for creating payment gateway instances based on gateway type.
 * Uses Symfony's service locator pattern for dependency injection.
 */
final readonly class PaymentGatewayFactory
{
    /**
     * @param array<string, PaymentGatewayInterface> $gateways
     */
    public function __construct(
        private array $gateways
    ) {
    }

    /**
     * Get payment gateway instance by gateway type.
     *
     * @throws \InvalidArgumentException If gateway is not supported
     */
    public function getGateway(PaymentGateway $gateway): PaymentGatewayInterface
    {
        $gatewayName = $gateway->value();

        if (!isset($this->gateways[$gatewayName])) {
            throw new \InvalidArgumentException(sprintf('Payment gateway "%s" is not supported. Available gateways: %s', $gatewayName, implode(', ', array_keys($this->gateways))));
        }

        return $this->gateways[$gatewayName];
    }

    /**
     * Check if gateway is supported.
     */
    public function supports(PaymentGateway $gateway): bool
    {
        return isset($this->gateways[$gateway->value()]);
    }

    /**
     * Get all supported gateway names.
     *
     * @return string[]
     */
    public function getSupportedGateways(): array
    {
        return array_keys($this->gateways);
    }
}
