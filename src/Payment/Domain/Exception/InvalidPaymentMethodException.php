<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

/**
 * Exception thrown when payment method is invalid or expired.
 *
 * This exception covers various payment method validation failures:
 * - Invalid card number
 * - Expired card
 * - Invalid expiry date
 * - Invalid CVC/CVV
 * - Unsupported card type
 * - Payment method not found or deleted
 *
 * Common Error Codes:
 * - invalid_card: Generic card validation failure
 * - invalid_card_number: Card number format invalid
 * - invalid_expiry_date: Expiry date invalid or in past
 * - invalid_cvc: CVC/CVV code invalid
 * - expired_card: Card has expired
 * - card_not_supported: Card type not accepted
 * - payment_method_not_found: Payment method ID doesn't exist
 */
final class InvalidPaymentMethodException extends PaymentGatewayException
{
    private function __construct(
        string $message,
        string $gatewayErrorCode,
        private readonly ?string $field = null,
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            gatewayErrorCode: $gatewayErrorCode,
            gatewayErrorMessage: $gatewayErrorMessage,
            declineCode: null,
            previous: $previous
        );
    }

    public static function invalidCard(
        ?string $reason = null,
        ?string $field = null,
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ): self {
        $message = $reason
            ? sprintf('Invalid payment method: %s', $reason)
            : 'Invalid payment method';

        return new self(
            message: $message,
            gatewayErrorCode: 'invalid_card',
            field: $field,
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    public static function invalidCardNumber(
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            message: 'Invalid card number',
            gatewayErrorCode: 'invalid_card_number',
            field: 'card_number',
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    public static function expiredCard(
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            message: 'Card has expired',
            gatewayErrorCode: 'expired_card',
            field: 'expiry_date',
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    public static function invalidExpiryDate(
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            message: 'Invalid card expiry date',
            gatewayErrorCode: 'invalid_expiry_date',
            field: 'expiry_date',
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    public static function invalidCvc(
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            message: 'Invalid security code (CVC/CVV)',
            gatewayErrorCode: 'invalid_cvc',
            field: 'cvc',
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    public static function cardNotSupported(
        ?string $cardType = null,
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ): self {
        $message = $cardType
            ? sprintf('Card type not supported: %s', $cardType)
            : 'Card type not supported';

        return new self(
            message: $message,
            gatewayErrorCode: 'card_not_supported',
            field: 'card_number',
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    public static function paymentMethodNotFound(
        string $paymentMethodId,
        ?string $gatewayErrorMessage = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            message: sprintf('Payment method not found: %s', $paymentMethodId),
            gatewayErrorCode: 'payment_method_not_found',
            field: null,
            gatewayErrorMessage: $gatewayErrorMessage,
            previous: $previous
        );
    }

    /**
     * Get the specific field that caused the validation error (if known)
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        return match ($this->gatewayErrorCode) {
            'invalid_card_number' => 'Invalid card number. Please check your card details.',
            'expired_card' => 'Your card has expired. Please use a different card.',
            'invalid_expiry_date' => 'Invalid expiry date. Please check your card details.',
            'invalid_cvc' => 'Invalid security code. Please check the CVC/CVV on your card.',
            'card_not_supported' => 'This card type is not supported. Please use a different card.',
            'payment_method_not_found' => 'Payment method not found. Please add a new payment method.',
            default => 'Invalid payment details. Please check your card information.',
        };
    }

    /**
     * This error is not retryable - customer must fix payment method
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Customer must update payment method
     */
    public function requiresCustomerAction(): bool
    {
        return true;
    }
}
