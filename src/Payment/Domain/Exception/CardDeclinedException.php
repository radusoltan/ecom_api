<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

/**
 * Exception thrown when a card payment is declined.
 *
 * Provides specific decline codes for detailed error handling.
 *
 * Common Decline Codes:
 * - generic_decline: Card declined for unspecified reasons
 * - insufficient_funds: Insufficient funds in account
 * - lost_card: Card reported lost
 * - stolen_card: Card reported stolen
 * - expired_card: Card has expired
 * - incorrect_cvc: Incorrect CVC/CVV code
 * - processing_error: Issuer processing error
 * - card_velocity_exceeded: Too many transactions in short period
 * - withdrawal_count_limit_exceeded: Daily withdrawal limit exceeded
 * - do_not_honor: Issuer declined without specific reason
 * - fraudulent: Transaction flagged as potentially fraudulent
 * - invalid_account: Card account is invalid
 * - new_account_information_available: Card replaced, new details needed
 */
final class CardDeclinedException extends PaymentGatewayException
{
    public static function create(
        string $declineCode,
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null,
    ): self {
        $message = self::getMessageForDeclineCode($declineCode);

        return new self(
            message: $message,
            gatewayErrorCode: 'card_declined',
            gatewayErrorMessage: $gatewayErrorMessage,
            declineCode: $declineCode,
            previous: $previous
        );
    }

    private static function getMessageForDeclineCode(string $declineCode): string
    {
        return match ($declineCode) {
            'insufficient_funds' => 'Payment declined: Insufficient funds in account',
            'lost_card' => 'Payment declined: Card reported lost',
            'stolen_card' => 'Payment declined: Card reported stolen',
            'expired_card' => 'Payment declined: Card has expired',
            'incorrect_cvc' => 'Payment declined: Incorrect security code (CVC/CVV)',
            'fraudulent' => 'Payment declined: Transaction flagged as potentially fraudulent',
            'do_not_honor' => 'Payment declined by card issuer',
            'card_velocity_exceeded' => 'Payment declined: Too many transactions in short period',
            'withdrawal_count_limit_exceeded' => 'Payment declined: Daily transaction limit exceeded',
            'invalid_account' => 'Payment declined: Invalid card account',
            'new_account_information_available' => 'Payment declined: Card has been replaced, please update card details',
            default => sprintf('Payment declined: %s', $declineCode),
        };
    }

    /**
     * Check if the decline is due to insufficient funds.
     */
    public function isInsufficientFunds(): bool
    {
        return 'insufficient_funds' === $this->declineCode;
    }

    /**
     * Check if the decline is due to fraud detection.
     */
    public function isFraudulent(): bool
    {
        return 'fraudulent' === $this->declineCode;
    }

    /**
     * Check if the decline is due to lost or stolen card.
     */
    public function isLostOrStolen(): bool
    {
        return in_array($this->declineCode, ['lost_card', 'stolen_card'], true);
    }

    /**
     * Check if the decline is due to expired card.
     */
    public function isExpired(): bool
    {
        return 'expired_card' === $this->declineCode;
    }

    /**
     * Check if retry with same card might succeed (temporary issue).
     */
    public function canRetryWithSameCard(): bool
    {
        // These are temporary/transient issues where retry might work
        return in_array($this->declineCode, [
            'processing_error',
            'card_velocity_exceeded',
            'do_not_honor',
        ], true);
    }

    /**
     * Check if customer must use different payment method.
     */
    public function requiresDifferentPaymentMethod(): bool
    {
        // These are permanent issues requiring different card/method
        return in_array($this->declineCode, [
            'insufficient_funds',
            'lost_card',
            'stolen_card',
            'expired_card',
            'fraudulent',
            'invalid_account',
        ], true);
    }
}
