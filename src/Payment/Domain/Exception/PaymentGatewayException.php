<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

use Exception;

/**
 * Base exception for payment gateway errors.
 *
 * This exception provides rich context about gateway failures for proper error handling
 * and user-friendly error messages.
 *
 * Common Error Codes:
 * - card_declined: Card was declined by issuer
 * - insufficient_funds: Insufficient funds in customer's account
 * - invalid_card: Card number or details are invalid
 * - expired_card: Card has expired
 * - processing_error: Gateway processing error
 * - authentication_required: 3D Secure/SCA authentication required
 * - rate_limit: Too many requests to gateway
 * - gateway_timeout: Gateway request timed out
 */
class PaymentGatewayException extends \Exception
{
    public function __construct(
        string $message,
        public readonly string $gatewayErrorCode,
        public readonly ?string $gatewayErrorMessage = null,
        public readonly ?string $declineCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the gateway-specific error code.
     */
    public function getGatewayErrorCode(): string
    {
        return $this->gatewayErrorCode;
    }

    /**
     * Get the raw gateway error message.
     */
    public function getGatewayErrorMessage(): ?string
    {
        return $this->gatewayErrorMessage;
    }

    /**
     * Get the decline code if card was declined.
     */
    public function getDeclineCode(): ?string
    {
        return $this->declineCode;
    }

    /**
     * Check if this is a temporary error that can be retried.
     */
    public function isRetryable(): bool
    {
        return in_array($this->gatewayErrorCode, [
            'processing_error',
            'gateway_timeout',
            'rate_limit',
            'temporary_failure',
        ], true);
    }

    /**
     * Check if customer action is required (e.g., update payment method).
     */
    public function requiresCustomerAction(): bool
    {
        return in_array($this->gatewayErrorCode, [
            'card_declined',
            'insufficient_funds',
            'invalid_card',
            'expired_card',
            'authentication_required',
        ], true);
    }

    /**
     * Get user-friendly error message for display.
     */
    public function getUserMessage(): string
    {
        return match ($this->gatewayErrorCode) {
            'card_declined' => 'Your card was declined. Please try a different payment method.',
            'insufficient_funds' => 'Insufficient funds. Please use a different payment method.',
            'invalid_card' => 'Invalid card details. Please check your card information.',
            'expired_card' => 'Your card has expired. Please use a different card.',
            'authentication_required' => 'Additional authentication is required to complete this payment.',
            'processing_error' => 'Payment processing error. Please try again.',
            'rate_limit' => 'Too many attempts. Please try again later.',
            'gateway_timeout' => 'Payment gateway timeout. Please try again.',
            default => 'Payment failed. Please contact support if the problem persists.',
        };
    }
}
