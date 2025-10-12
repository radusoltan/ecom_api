<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentMethod;
use Psr\Log\LoggerInterface;

/**
 * Fake PayPal Payment Gateway for Testing
 *
 * Simulates PayPal API responses without making real HTTP calls.
 * Used in test environment to avoid dependency on external services.
 */
final readonly class FakePayPalGateway implements PaymentGatewayInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function authorize(
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        array $metadata = []
    ): array {
        $this->logger->info('[FAKE] PayPal: Authorizing payment', [
            'amount' => $amountInCents,
            'currency' => $currency,
            'method' => $method->value(),
            'metadata' => $metadata,
        ]);

        // Generate fake transaction ID (PayPal format)
        $transactionId = 'PAYID-' . strtoupper(bin2hex(random_bytes(16)));

        return [
            'transaction_id' => $transactionId,
            'status' => 'AUTHORIZED',
            'metadata' => [
                'payer_id' => 'PAYER' . bin2hex(random_bytes(6)),
                'amount' => $amountInCents,
                'currency' => strtoupper($currency),
            ],
        ];
    }

    public function capture(string $transactionId, ?int $amountInCents = null): array
    {
        $this->logger->info('[FAKE] PayPal: Capturing payment', [
            'transaction_id' => $transactionId,
            'amount' => $amountInCents,
        ]);

        // Simulate successful capture
        return [
            'transaction_id' => $transactionId,
            'status' => 'COMPLETED',
            'captured_amount' => $amountInCents ?? 9999, // Default if not specified
        ];
    }

    public function refund(string $transactionId, int $amountInCents, string $reason): array
    {
        $this->logger->info('[FAKE] PayPal: Processing refund', [
            'transaction_id' => $transactionId,
            'amount' => $amountInCents,
            'reason' => $reason,
        ]);

        // Generate fake refund ID
        $refundId = 'REFUND-' . strtoupper(bin2hex(random_bytes(12)));

        return [
            'refund_id' => $refundId,
            'status' => 'COMPLETED',
            'refunded_amount' => $amountInCents,
        ];
    }

    public function cancel(string $transactionId, string $reason): array
    {
        $this->logger->info('[FAKE] PayPal: Cancelling payment', [
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ]);

        return [
            'status' => 'VOIDED',
        ];
    }

    public function getStatus(string $transactionId): array
    {
        $this->logger->info('[FAKE] PayPal: Getting payment status', [
            'transaction_id' => $transactionId,
        ]);

        return [
            'status' => 'AUTHORIZED',
            'amount' => 9999,
            'currency' => 'USD',
            'metadata' => [
                'authorization_id' => $transactionId,
                'create_time' => date('c'),
                'update_time' => date('c'),
            ],
        ];
    }

    public function getName(): string
    {
        return 'paypal';
    }
}
