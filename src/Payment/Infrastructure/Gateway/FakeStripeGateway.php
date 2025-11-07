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
 *
 * 3D Secure (3DS) Support:
 * - Payment method types: ['card'] with 3DS enabled by default
 * - Handles `requires_action` status for Strong Customer Authentication (SCA)
 * - Returns action_url for 3DS challenges when card requires authentication
 * - Supports payment confirmation after 3DS completion
 *
 * Stripe 3DS Flow:
 * 1. authorize() may return 'requires_action' status if 3DS is required
 * 2. Client redirects customer to action_url for authentication
 * 3. After authentication, call getStatus() to check if status changed to 'requires_capture'
 * 4. Call capture() to complete payment
 *
 * @see https://stripe.com/docs/payments/3d-secure
 * @see https://stripe.com/docs/strong-customer-authentication
 */
final readonly class FakeStripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $require3DS = false // Simulate 3DS requirement for testing
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
            'payment_method_types' => ['card'],
            '3ds_enabled' => true,
        ]);

        // Generate fake transaction ID
        $transactionId = 'pi_fake_' . bin2hex(random_bytes(12));

        // Simulate 3D Secure requirement (SCA - Strong Customer Authentication)
        if ($this->require3DS || ($metadata['force_3ds'] ?? false)) {
            $this->logger->info('[FAKE] Stripe: 3DS authentication required', [
                'transaction_id' => $transactionId,
            ]);

            return [
                'transaction_id' => $transactionId,
                'status' => 'requires_action',
                'next_action' => [
                    'type' => 'redirect_to_url',
                    'redirect_to_url' => [
                        'url' => 'https://hooks.stripe.com/3d_secure/' . $transactionId,
                        'return_url' => $metadata['return_url'] ?? 'https://example.com/payment/confirm',
                    ],
                ],
                'metadata' => [
                    'client_secret' => $transactionId . '_secret_fake',
                    'amount' => $amountInCents,
                    'currency' => strtolower($currency),
                    'requires_3ds' => true,
                ],
            ];
        }

        // Standard authorization without 3DS
        return [
            'transaction_id' => $transactionId,
            'status' => 'requires_capture',
            'metadata' => [
                'client_secret' => $transactionId . '_secret_fake',
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
                'payment_method_types' => ['card'],
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

        // Simulate successful 3DS authentication (status transition)
        // In real Stripe, after 3DS completion, status changes from 'requires_action' to 'requires_capture'
        $status = $this->require3DS ? 'requires_capture' : 'requires_capture';

        return [
            'status' => $status,
            'amount' => 9999,
            'currency' => 'USD',
            'metadata' => [
                'amount_received' => 0,
                'amount_capturable' => 9999,
                'created' => time(),
                '3ds_authenticated' => $this->require3DS,
            ],
        ];
    }

    public function getName(): string
    {
        return 'stripe';
    }
}
