<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\CreatePaymentIntent;

use App\Payment\Domain\Model\PaymentId;

/**
 * Result DTO for CreatePaymentIntent command.
 *
 * Immutable value object representing the outcome of creating a payment intent.
 * Uses factory methods for different result states.
 */
final readonly class CreatePaymentIntentResult
{
    private function __construct(
        public bool $success,
        public PaymentId $paymentId,
        public ?string $clientSecret,
        public ?string $gatewayPaymentId,
        public ?string $errorCode,
        public ?string $errorMessage,
        public bool $alreadyExists = false,
    ) {
    }

    /**
     * Factory method for successful payment intent creation.
     */
    public static function success(
        PaymentId $paymentId,
        string $clientSecret,
        string $gatewayPaymentId,
    ): self {
        return new self(
            success: true,
            paymentId: $paymentId,
            clientSecret: $clientSecret,
            gatewayPaymentId: $gatewayPaymentId,
            errorCode: null,
            errorMessage: null,
            alreadyExists: false
        );
    }

    /**
     * Factory method when payment intent already exists (idempotency).
     */
    public static function alreadyExists(
        PaymentId $paymentId,
        ?string $gatewayPaymentId,
    ): self {
        return new self(
            success: true,
            paymentId: $paymentId,
            clientSecret: null,
            gatewayPaymentId: $gatewayPaymentId,
            errorCode: null,
            errorMessage: null,
            alreadyExists: true
        );
    }

    /**
     * Factory method for failed payment intent creation.
     */
    public static function failed(
        ?string $errorCode,
        ?string $errorMessage,
    ): self {
        return new self(
            success: false,
            paymentId: PaymentId::generate(), // Temporary ID for failed attempts
            clientSecret: null,
            gatewayPaymentId: null,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            alreadyExists: false
        );
    }

    /**
     * Check if payment intent was created successfully.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if payment intent already existed.
     */
    public function wasAlreadyCreated(): bool
    {
        return $this->alreadyExists;
    }

    /**
     * Check if result represents a failure.
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }
}
