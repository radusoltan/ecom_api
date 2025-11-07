<?php

declare(strict_types=1);

namespace App\Payment\Domain\Service;

use App\Payment\Domain\ValueObject\PaymentMethod;

/**
 * Payment Gateway Interface (Domain Service Port).
 *
 * This interface defines the contract for payment gateway integrations.
 * Implementations are adapters in the infrastructure layer.
 */
interface PaymentGatewayInterface
{
    /**
     * Authorize a payment (reserve funds without capturing).
     *
     * @param int                  $amountInCents Amount to authorize in smallest currency unit (cents)
     * @param string               $currency      ISO 4217 currency code (USD, EUR, GBP)
     * @param PaymentMethod        $method        Payment method (card, paypal, etc.)
     * @param array<string, mixed> $metadata      Additional payment metadata (customer ID, order ID, etc.)
     *
     * @return array{transaction_id: string, status: string, metadata?: array<string, mixed>}
     *
     * @throws \RuntimeException If authorization fails
     */
    public function authorize(
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        array $metadata = []
    ): array;

    /**
     * Capture a previously authorized payment.
     *
     * @param string   $transactionId Gateway transaction ID from authorization
     * @param int|null $amountInCents Amount to capture (null = full amount)
     *
     * @return array{transaction_id: string, status: string, captured_amount: int}
     *
     * @throws \RuntimeException If capture fails
     */
    public function capture(
        string $transactionId,
        ?int $amountInCents = null
    ): array;

    /**
     * Refund a captured payment.
     *
     * @param string $transactionId Gateway transaction ID from capture
     * @param int    $amountInCents Amount to refund (partial refunds supported)
     * @param string $reason        Refund reason for audit trail
     *
     * @return array{refund_id: string, status: string, refunded_amount: int}
     *
     * @throws \RuntimeException If refund fails
     */
    public function refund(
        string $transactionId,
        int $amountInCents,
        string $reason
    ): array;

    /**
     * Cancel an authorized payment (void).
     *
     * @param string $transactionId Gateway transaction ID from authorization
     * @param string $reason        Cancellation reason for audit trail
     *
     * @return array{status: string}
     *
     * @throws \RuntimeException If cancellation fails
     */
    public function cancel(
        string $transactionId,
        string $reason
    ): array;

    /**
     * Get payment status from gateway.
     *
     * @param string $transactionId Gateway transaction ID
     *
     * @return array{status: string, amount: int, currency: string, metadata?: array<string, mixed>}
     *
     * @throws \RuntimeException If status retrieval fails
     */
    public function getStatus(string $transactionId): array;

    /**
     * Get the gateway name.
     *
     * @return string Gateway identifier (stripe, paypal, etc.)
     */
    public function getName(): string;
}
