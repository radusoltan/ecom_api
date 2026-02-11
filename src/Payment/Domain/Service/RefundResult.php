<?php

declare(strict_types=1);

namespace App\Payment\Domain\Service;

use App\Shared\Domain\ValueObject\Money;

/**
 * Result DTO from refund operations.
 *
 * Immutable value object representing the outcome of payment refund operations.
 *
 * Refund Status:
 * - pending: Refund initiated, awaiting processing
 * - succeeded: Refund completed successfully
 * - failed: Refund failed (insufficient funds, etc.)
 * - canceled: Refund was cancelled
 */
final readonly class RefundResult
{
    private function __construct(
        public bool $success,
        public ?string $gatewayRefundId,
        public string $status,
        public Money $amount,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?string $rawResponse
    ) {
    }

    public static function success(
        string $gatewayRefundId,
        string $status,
        Money $amount,
        ?string $rawResponse = null
    ): self {
        return new self(
            success: true,
            gatewayRefundId: $gatewayRefundId,
            status: $status,
            amount: $amount,
            errorCode: null,
            errorMessage: null,
            rawResponse: $rawResponse
        );
    }

    public static function failure(
        string $errorCode,
        string $errorMessage,
        ?string $rawResponse = null
    ): self {
        return new self(
            success: false,
            gatewayRefundId: null,
            status: 'failed',
            amount: Money::fromScalars(0, 'USD'),
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse
        );
    }

    /**
     * Check if refund is pending processing
     */
    public function isPending(): bool
    {
        return 'pending' === $this->status;
    }

    /**
     * Check if refund succeeded
     */
    public function isSucceeded(): bool
    {
        return 'succeeded' === $this->status;
    }

    /**
     * Check if refund failed
     */
    public function isFailed(): bool
    {
        return 'failed' === $this->status;
    }

    /**
     * Check if refund was cancelled
     */
    public function isCanceled(): bool
    {
        return 'canceled' === $this->status;
    }

    /**
     * Check if refund is in a final state (cannot be modified)
     */
    public function isFinal(): bool
    {
        return in_array($this->status, ['succeeded', 'failed', 'canceled'], true);
    }
}
