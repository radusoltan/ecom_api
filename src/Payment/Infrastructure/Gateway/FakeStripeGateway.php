<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentMethod;
use Psr\Log\LoggerInterface;

/**
 * Fake Stripe Payment Gateway for Testing
 *
 * Simulates Stripe API responses without making real HTTP calls.
 * Used in test environment to avoid dependency on external services.
 */
final readonly class FakeStripeGateway implements PaymentGatewayInterface
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
        $this->logger->info('[FAKE] Stripe: Authorizing payment', [
            'amount' => $amountInCents,
            'currency' => $currency,
            'method' => $method->value(),
            'metadata' => $metadata,
        ]);

        // Generate fake transaction ID
        $transactionId = 'pi_fake_' . bin2hex(random_bytes(12));

        return [
            'transaction_id' => $transactionId,
            'status' => 'requires_capture',
            'metadata' => [
                'client_secret' => $transactionId . '_secret_fake',
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
            ],
        ];
    }

    public function capture(string $transactionId, ?int $amountInCents = null): array
    {
        $this->logger->info('[FAKE] Stripe: Capturing payment', [
            'transaction_id' => $transactionId,
            'amount' => $amountInCents,
        ]);

        // Simulate successful capture
        return [
            'transaction_id' => $transactionId,
            'status' => 'succeeded',
            'captured_amount' => $amountInCents ?? 9999, // Default if not specified
        ];
    }

    public function refund(string $transactionId, int $amountInCents, string $reason): array
    {
        $this->logger->info('[FAKE] Stripe: Processing refund', [
            'transaction_id' => $transactionId,
            'amount' => $amountInCents,
            'reason' => $reason,
        ]);

        // Generate fake refund ID
        $refundId = 're_fake_' . bin2hex(random_bytes(12));

        return [
            'refund_id' => $refundId,
            'status' => 'succeeded',
            'refunded_amount' => $amountInCents,
        ];
    }

    public function cancel(string $transactionId, string $reason): array
    {
        $this->logger->info('[FAKE] Stripe: Cancelling payment', [
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ]);

        return [
            'status' => 'canceled',
        ];
    }

    public function getStatus(string $transactionId): array
    {
        $this->logger->info('[FAKE] Stripe: Getting payment status', [
            'transaction_id' => $transactionId,
        ]);

        return [
            'status' => 'requires_capture',
            'amount' => 9999,
            'currency' => 'USD',
            'metadata' => [
                'amount_received' => 0,
                'amount_capturable' => 9999,
                'created' => time(),
            ],
        ];
    }

    public function getName(): string
    {
        return 'stripe';
    }
}
