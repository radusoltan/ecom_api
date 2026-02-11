<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Exception\UnsupportedPaymentMethodException;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayFactoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;

/**
 * Payment Gateway Factory - creates appropriate gateway adapter based on payment method.
 *
 * Implements Factory Pattern and Strategy Pattern:
 * - Factory Pattern: Creates gateway instances based on payment method enum
 * - Strategy Pattern: Returns different PaymentGatewayInterface implementations
 *
 * Design Principles:
 * - Dependency Injection: Gateway adapters injected in constructor (autowired by Symfony)
 * - Fail-fast: Throws exception for unsupported payment methods
 * - Type-safe: Uses PaymentMethod enum instead of strings
 * - Extensible: Add new gateways by injecting them in constructor
 *
 * Architecture:
 * - Port: PaymentGatewayFactoryInterface (in Domain layer)
 * - Adapter: This class (in Infrastructure layer)
 * - Concrete Gateways: StripeGateway, PayPalGatewayAdapter (in Infrastructure layer)
 *
 * Usage Pattern:
 * ```php
 * // In application layer (command handler, service)
 * final class ProcessPaymentHandler
 * {
 *     public function __construct(
 *         private readonly PaymentGatewayFactoryInterface $gatewayFactory
 *     ) {}
 *
 *     public function __invoke(ProcessPayment $command): void
 *     {
 *         $payment = $this->repository->find($command->paymentId);
 *         $gateway = $this->gatewayFactory->create($payment->method());
 *         $result = $gateway->createPaymentIntent(...);
 *     }
 * }
 * ```
 *
 * Registered Gateways:
 * - Stripe (PaymentMethod::STRIPE) → StripeGateway → Credit/debit cards
 * - PayPal (PaymentMethod::PAYPAL) → PayPalGatewayAdapter → PayPal accounts
 * - Bank Transfer (PaymentMethod::BANK_TRANSFER) → Not yet implemented
 *
 * Future Gateways:
 * - Apple Pay → Will use Stripe SDK (add new enum case)
 * - Google Pay → Will use Stripe SDK (add new enum case)
 * - Crypto → New adapter needed
 */
final class PaymentGatewayFactory implements PaymentGatewayFactoryInterface
{
    /**
     * @param StripeGateway        $stripeGateway Stripe payment gateway for card payments
     * @param PayPalGatewayAdapter $paypalGateway PayPal payment gateway for PayPal account payments
     */
    public function __construct(
        private readonly StripeGateway $stripeGateway,
        private readonly PayPalGatewayAdapter $paypalGateway,
    ) {
    }

    /**
     * Create a payment gateway adapter for the specified payment method.
     *
     * Uses match expression (PHP 8.1+) for exhaustive enum matching.
     * Compiler ensures all PaymentMethod cases are handled.
     *
     * @param PaymentMethod $method Payment method enum
     *
     * @return PaymentGatewayInterface Concrete gateway adapter
     *
     * @throws UnsupportedPaymentMethodException If payment method is not yet implemented
     */
    public function create(PaymentMethod $method): PaymentGatewayInterface
    {
        return match ($method) {
            PaymentMethod::STRIPE => $this->stripeGateway,
            PaymentMethod::PAYPAL => $this->paypalGateway,
            PaymentMethod::APPLE_PAY => $this->stripeGateway,  // Apple Pay uses Stripe SDK
            PaymentMethod::GOOGLE_PAY => $this->stripeGateway,  // Google Pay uses Stripe SDK
            PaymentMethod::BANK_TRANSFER => throw new UnsupportedPaymentMethodException($method),
        };
    }

    /**
     * Check if a payment method is supported by the factory.
     *
     * Returns true if gateway adapter exists and is active.
     * Use this before calling create() to avoid exceptions.
     *
     * @param PaymentMethod $method Payment method to check
     *
     * @return bool True if gateway is available
     */
    public function supports(PaymentMethod $method): bool
    {
        return match ($method) {
            PaymentMethod::STRIPE => true,   // Active
            PaymentMethod::PAYPAL => true,   // Active
            PaymentMethod::APPLE_PAY => true,   // Active (via Stripe)
            PaymentMethod::GOOGLE_PAY => true,   // Active (via Stripe)
            PaymentMethod::BANK_TRANSFER => false,  // Not yet implemented
        };
    }

    /**
     * Get list of all available payment gateways.
     *
     * Returns only payment methods with active gateway implementations.
     * Frontend should use this to display available payment options to customers.
     *
     * Example Response:
     * ```php
     * [
     *     PaymentMethod::STRIPE,  // Credit/debit cards
     *     PaymentMethod::PAYPAL,  // PayPal accounts
     * ]
     * ```
     *
     * @return array<int, PaymentMethod> Array of supported PaymentMethod enums
     */
    public function getAvailableGateways(): array
    {
        $available = [];

        foreach (PaymentMethod::cases() as $method) {
            if ($this->supports($method)) {
                $available[] = $method;
            }
        }

        return $available;
    }
}
