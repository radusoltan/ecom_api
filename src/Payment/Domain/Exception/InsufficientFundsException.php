<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

use App\Shared\Domain\ValueObject\Money;

/**
 * Exception thrown when payment fails due to insufficient funds.
 *
 * This is a specialized exception for the common case of insufficient funds,
 * providing additional context about the attempted and available amounts.
 */
final class InsufficientFundsException extends PaymentGatewayException
{
    private function __construct(
        string $message,
        private readonly ?Money $attemptedAmount = null,
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            gatewayErrorCode: 'insufficient_funds',
            gatewayErrorMessage: $gatewayErrorMessage,
            declineCode: 'insufficient_funds',
            previous: $previous
        );
    }

    public static function create(
        ?Money $attemptedAmount = null,
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null,
    ): self {
        $message = self::buildMessage($attemptedAmount);

        return new self(
            message: $message,
            attemptedAmount: $attemptedAmount,
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    private static function buildMessage(?Money $attemptedAmount): string
    {
        if (null === $attemptedAmount) {
            return 'Payment declined: Insufficient funds';
        }

        return sprintf(
            'Payment declined: Insufficient funds for amount %s',
            $attemptedAmount
        );
    }

    /**
     * Get the amount that was attempted to charge.
     */
    public function getAttemptedAmount(): ?Money
    {
        return $this->attemptedAmount;
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserMessage(): string
    {
        if (null === $this->attemptedAmount) {
            return 'Your payment was declined due to insufficient funds. Please use a different payment method.';
        }

        return sprintf(
            'Your payment of %s was declined due to insufficient funds. Please use a different payment method.',
            $this->attemptedAmount
        );
    }

    /**
     * This error is not retryable with the same payment method.
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Customer must provide different payment method.
     */
    public function requiresCustomerAction(): bool
    {
        return true;
    }
}
