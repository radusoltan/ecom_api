<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentMethod;

/**
 * Mock Payment Gateway for Testing
 *
 * Returns successful responses without making actual API calls.
 * Used in test environment to avoid external dependencies.
 */
final class MockPaymentGateway implements PaymentGatewayInterface
{
    public function authorize(
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        array $metadata = []
    ): array {
        // Simulate successful authorization
        return [
            'transaction_id' => 'mock_pi_' . bin2hex(random_bytes(12)),
            'status' => 'requires_capture',
            'metadata' => $metadata,
        ];
    }

    public function capture(
        string $transactionId,
        ?int $amountInCents = null
    ): array {
        // Simulate successful capture
        return [
            'transaction_id' => $transactionId,
            'status' => 'succeeded',
            'captured_amount' => $amountInCents ?? 0,
        ];
    }

    public function refund(
        string $transactionId,
        int $amountInCents,
        string $reason
    ): array {
        // Simulate successful refund
        return [
            'refund_id' => 'mock_re_' . bin2hex(random_bytes(12)),
            'status' => 'succeeded',
            'refunded_amount' => $amountInCents,
        ];
    }

    public function cancel(
        string $transactionId,
        string $reason
    ): array {
        // Simulate successful cancellation
        return [
            'status' => 'canceled',
        ];
    }

    public function getStatus(string $transactionId): array
    {
        // Simulate status retrieval
        return [
            'status' => 'succeeded',
            'amount' => 10000,
            'currency' => 'USD',
            'metadata' => [],
        ];
    }

    public function getName(): string
    {
        return 'mock';
    }
}
