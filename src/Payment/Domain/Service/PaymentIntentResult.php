<?php

declare(strict_types=1);

namespace App\Payment\Domain\Service;

use App\Shared\Domain\ValueObject\Money;

/**
 * Result DTO from payment intent operations.
 *
 * Immutable value object representing the outcome of payment gateway operations
 * like authorization, confirmation, and capture.
 *
 * Gateway Status Mapping:
 * - requires_payment_method: Customer needs to provide payment details
 * - requires_confirmation: Payment method provided, awaiting confirmation
 * - requires_action: Additional action needed (3D Secure, SCA)
 * - processing: Payment being processed by gateway
 * - requires_capture: Authorized, waiting for capture
 * - succeeded: Payment completed successfully
 * - canceled: Payment was cancelled/voided
 */
final readonly class PaymentIntentResult
{
    private function __construct(
        public bool $success,
        public string $gatewayPaymentIntentId,
        public string $status,
        public Money $amount,
        public ?string $clientSecret,
        public ?string $customerId,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?string $rawResponse,
    ) {
    }

    public static function success(
        string $gatewayPaymentIntentId,
        string $status,
        Money $amount,
        ?string $clientSecret = null,
        ?string $customerId = null,
        ?string $rawResponse = null,
    ): self {
        return new self(
            success: true,
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            status: $status,
            amount: $amount,
            clientSecret: $clientSecret,
            customerId: $customerId,
            errorCode: null,
            errorMessage: null,
            rawResponse: $rawResponse
        );
    }

    public static function failure(
        string $errorCode,
        string $errorMessage,
        ?string $gatewayPaymentIntentId = null,
        ?string $rawResponse = null,
    ): self {
        return new self(
            success: false,
            gatewayPaymentIntentId: $gatewayPaymentIntentId ?? '',
            status: 'failed',
            amount: Money::fromScalars(0, 'USD'),
            clientSecret: null,
            customerId: null,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse
        );
    }

    /**
     * Check if payment requires additional action (3D Secure, SCA, etc.).
     */
    public function requiresAction(): bool
    {
        return 'requires_action' === $this->status;
    }

    /**
     * Check if payment has been authorized (funds reserved).
     */
    public function isAuthorized(): bool
    {
        return in_array($this->status, ['requires_capture', 'processing'], true);
    }

    /**
     * Check if payment has been captured (funds transferred).
     */
    public function isCaptured(): bool
    {
        return 'succeeded' === $this->status;
    }

    /**
     * Check if payment is in a final state (cannot be modified).
     */
    public function isFinal(): bool
    {
        return in_array($this->status, ['succeeded', 'canceled', 'failed'], true);
    }

    /**
     * Check if payment requires confirmation.
     */
    public function requiresConfirmation(): bool
    {
        return 'requires_confirmation' === $this->status;
    }

    /**
     * Check if payment is still processing.
     */
    public function isProcessing(): bool
    {
        return 'processing' === $this->status;
    }
}
