<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\Model\PaymentMethod;

/**
 * Exception thrown when attempting to create a gateway for an unsupported payment method.
 *
 * This exception is raised by PaymentGatewayFactory when:
 * - Payment method enum exists but has no registered gateway adapter
 * - Payment method is planned for future implementation but not yet available
 * - Payment method configuration is invalid
 *
 * Business Context:
 * - Customer selects payment method during checkout
 * - Application attempts to create gateway adapter
 * - If gateway doesn't exist → exception thrown
 * - Frontend should hide unavailable payment methods to prevent this
 *
 * Recovery Strategy:
 * - Catch this exception in application layer
 * - Return user-friendly error message
 * - Log for monitoring (may indicate configuration issue)
 * - Redirect customer to payment method selection
 *
 * Example:
 * ```php
 * try {
 *     $gateway = $factory->create(PaymentMethod::BANK_TRANSFER);
 * } catch (UnsupportedPaymentMethodException $e) {
 *     // Log error and show message to user
 *     $this->logger->warning('Unsupported payment method selected', [
 *         'method' => $e->getPaymentMethod()->value(),
 *     ]);
 * }
 * ```
 */
final class UnsupportedPaymentMethodException extends \RuntimeException
{
    private PaymentMethod $paymentMethod;

    public function __construct(PaymentMethod $paymentMethod, ?\Throwable $previous = null)
    {
        $this->paymentMethod = $paymentMethod;

        $message = sprintf(
            'Payment method "%s" is not supported. Available methods: Stripe (credit_card), PayPal (paypal). Please check gateway configuration.',
            $paymentMethod->value()
        );

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the unsupported payment method that caused this exception.
     *
     * @return PaymentMethod The payment method enum value
     */
    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    /**
     * Get user-friendly error message for API responses.
     *
     * @return string Sanitized message safe to display to customers
     */
    public function getUserMessage(): string
    {
        return sprintf(
            'The selected payment method "%s" is currently not available. Please choose a different payment method.',
            $this->paymentMethod->description()
        );
    }
}
