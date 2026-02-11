<?php

declare(strict_types=1);

namespace App\Payment\Domain\Service;

use App\Payment\Domain\Exception\UnsupportedPaymentMethodException;
use App\Payment\Domain\Model\PaymentMethod;

/**
 * Payment Gateway Factory Port - creates appropriate gateway adapter based on payment method.
 *
 * This interface follows the Factory Pattern and Strategy Pattern:
 * - Factory Pattern: Creates gateway instances based on payment method
 * - Strategy Pattern: Returns different implementations of PaymentGatewayInterface
 *
 * Design Principles:
 * - Port in Domain layer (no framework dependencies)
 * - Adapter in Infrastructure layer (concrete factory implementation)
 * - Dependency Injection: Gateway adapters injected into factory constructor
 * - Fail-fast: Throws exception for unsupported payment methods
 *
 * Usage Example:
 * ```php
 * $gateway = $this->gatewayFactory->create(PaymentMethod::STRIPE);
 * $result = $gateway->createPaymentIntent($paymentId, $amount, ...);
 * ```
 *
 * Supported Gateways:
 * - Stripe: Credit/debit card payments (PaymentMethod::STRIPE)
 * - PayPal: PayPal account payments (PaymentMethod::PAYPAL)
 * - Future: Bank transfer, Apple Pay, Google Pay
 *
 * @see PaymentGatewayInterface
 * @see PaymentMethod
 */
interface PaymentGatewayFactoryInterface
{
    /**
     * Create a payment gateway adapter for the specified payment method.
     *
     * Factory method that returns the appropriate gateway implementation
     * based on the payment method enum.
     *
     * @param PaymentMethod $method Payment method enum (STRIPE, PAYPAL, BANK_TRANSFER)
     *
     * @return PaymentGatewayInterface Concrete gateway adapter (StripeGatewayAdapter, PayPalGatewayAdapter)
     *
     * @throws UnsupportedPaymentMethodException If payment method is not supported or not yet implemented
     */
    public function create(PaymentMethod $method): PaymentGatewayInterface;

    /**
     * Check if a payment method is supported by the factory.
     *
     * Use this method to validate payment methods before attempting to create a gateway.
     *
     * @param PaymentMethod $method Payment method to check
     *
     * @return bool True if the payment method has a registered gateway adapter
     */
    public function supports(PaymentMethod $method): bool;

    /**
     * Get list of all available payment gateways.
     *
     * Returns payment methods that have active gateway implementations.
     * Useful for displaying available payment options to customers.
     *
     * @return array<int, PaymentMethod> Array of supported PaymentMethod enums
     */
    public function getAvailableGateways(): array;
}
