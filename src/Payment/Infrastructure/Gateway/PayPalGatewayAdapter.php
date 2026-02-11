<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Exception\PaymentGatewayException;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\Service\RefundResult;
use App\Shared\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;

/**
 * PayPal Gateway Adapter - implements PaymentGatewayInterface using PayPalClient.
 *
 * This adapter converts between domain models and PayPal's API format:
 * - Domain Payment → PayPal Order
 * - PaymentIntentResult ← PayPal Order Response
 * - RefundResult ← PayPal Refund Response
 *
 * PayPal Payment Flow:
 * 1. createPaymentIntent() → PayPal createOrder() with AUTHORIZE intent
 * 2. confirmPaymentIntent() → Customer approves on PayPal (client-side redirect)
 * 3. capturePaymentIntent() → PayPal captureOrder() to transfer funds
 * 4. createRefund() → PayPal createRefund() to return funds
 * 5. cancelPaymentIntent() → Cancel order (no funds captured yet)
 *
 * PayPal Status Mapping:
 * - CREATED → requires_payment_method (awaiting customer approval)
 * - APPROVED → requires_capture (authorized, ready to capture)
 * - COMPLETED → succeeded (funds captured)
 * - VOIDED → canceled (authorization voided)
 * - PAYER_ACTION_REQUIRED → requires_action (3D Secure, etc.)
 *
 * Error Handling:
 * - Network errors → PaymentGatewayException
 * - Invalid responses → PaymentGatewayException
 * - Business errors (insufficient funds) → Gracefully mapped to failure result
 */
final class PayPalGatewayAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly PayPalClient $client,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createPaymentIntent(
        PaymentId $paymentId,
        Money $amount,
        string $currency,
        string $idempotencyKey,
        ?string $customerId = null,
        array $metadata = []
    ): PaymentIntentResult {
        try {
            // Convert domain Money to PayPal format (dollars with 2 decimals)
            $amountValue = number_format($amount->getAmount() / 100, 2, '.', '');

            // Build PayPal order payload
            $orderData = [
                'intent' => 'AUTHORIZE', // Authorize-then-capture flow
                'purchase_units' => [
                    [
                        'reference_id' => $paymentId->toString(),
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => $amountValue,
                        ],
                        'custom_id' => $idempotencyKey, // For idempotency tracking
                    ],
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'return_url' => $metadata['return_url'] ?? 'https://example.com/success',
                            'cancel_url' => $metadata['cancel_url'] ?? 'https://example.com/cancel',
                        ],
                    ],
                ],
            ];

            // Add customer ID if provided (PayPal customer reference)
            if (null !== $customerId) {
                $orderData['payer'] = [
                    'payer_id' => $customerId,
                ];
            }

            $this->logger->info('Creating PayPal order', [
                'payment_id' => $paymentId->toString(),
                'amount' => $amountValue,
                'currency' => $currency,
            ]);

            $response = $this->client->createOrder($orderData);

            $orderId = $response['id'] ?? null;
            $status = $response['status'] ?? 'UNKNOWN';

            if (null === $orderId) {
                throw new PaymentGatewayException('PayPal order creation failed: Missing order ID', 'paypal_order_creation_failed');
            }

            $this->logger->info('PayPal order created successfully', [
                'order_id' => $orderId,
                'status' => $status,
            ]);

            // Map PayPal status to domain status
            $domainStatus = $this->mapPayPalStatusToDomain($status);

            // Extract approval URL for client (customer redirects here to approve)
            $approvalUrl = $this->extractApprovalUrl($response);

            return PaymentIntentResult::success(
                gatewayPaymentIntentId: $orderId,
                status: $domainStatus,
                amount: $amount,
                clientSecret: $approvalUrl, // Client needs this URL for customer approval
                customerId: $customerId,
                rawResponse: json_encode($response) ?: null
            );
        } catch (\Exception $e) {
            $this->logger->error('PayPal order creation failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId->toString(),
            ]);

            throw new PaymentGatewayException(sprintf('Failed to create PayPal order: %s', $e->getMessage()), 'paypal_order_creation_failed', $e->getMessage(), null, $e);
        }
    }

    public function confirmPaymentIntent(
        string $gatewayPaymentIntentId,
        string $paymentMethodId
    ): PaymentIntentResult {
        try {
            // For PayPal, confirmation happens client-side (customer redirects to PayPal)
            // We just retrieve the order to check if it's approved
            $this->logger->info('Confirming PayPal order', [
                'order_id' => $gatewayPaymentIntentId,
            ]);

            $response = $this->client->getOrder($gatewayPaymentIntentId);

            $status = $response['status'] ?? 'UNKNOWN';
            $domainStatus = $this->mapPayPalStatusToDomain($status);

            // Extract amount from response
            $amount = $this->extractAmountFromOrder($response);

            $this->logger->info('PayPal order status retrieved', [
                'order_id' => $gatewayPaymentIntentId,
                'status' => $status,
            ]);

            return PaymentIntentResult::success(
                gatewayPaymentIntentId: $gatewayPaymentIntentId,
                status: $domainStatus,
                amount: $amount,
                clientSecret: null,
                customerId: null,
                rawResponse: json_encode($response) ?: null
            );
        } catch (\Exception $e) {
            $this->logger->error('PayPal order confirmation failed', [
                'error' => $e->getMessage(),
                'order_id' => $gatewayPaymentIntentId,
            ]);

            return PaymentIntentResult::failure(
                errorCode: 'confirmation_failed',
                errorMessage: $e->getMessage(),
                gatewayPaymentIntentId: $gatewayPaymentIntentId,
                rawResponse: null
            );
        }
    }

    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null
    ): PaymentIntentResult {
        try {
            $this->logger->info('Capturing PayPal order', [
                'order_id' => $gatewayPaymentIntentId,
                'amount' => $amount?->getAmount(),
            ]);

            $response = $this->client->captureOrder($gatewayPaymentIntentId);

            $status = $response['status'] ?? 'UNKNOWN';
            $domainStatus = $this->mapPayPalStatusToDomain($status);

            // Extract captured amount
            $capturedAmount = $this->extractAmountFromOrder($response);

            // Extract capture ID for refunds later
            $captureId = $this->extractCaptureId($response);

            $this->logger->info('PayPal order captured successfully', [
                'order_id' => $gatewayPaymentIntentId,
                'status' => $status,
                'capture_id' => $captureId,
            ]);

            return PaymentIntentResult::success(
                gatewayPaymentIntentId: $gatewayPaymentIntentId,
                status: $domainStatus,
                amount: $capturedAmount,
                clientSecret: $captureId, // Store capture ID for refunds
                customerId: null,
                rawResponse: json_encode($response) ?: null
            );
        } catch (\Exception $e) {
            $this->logger->error('PayPal order capture failed', [
                'error' => $e->getMessage(),
                'order_id' => $gatewayPaymentIntentId,
            ]);

            throw new PaymentGatewayException(sprintf('Failed to capture PayPal order: %s', $e->getMessage()), 'paypal_capture_failed', $e->getMessage(), null, $e);
        }
    }

    public function cancelPaymentIntent(
        string $gatewayPaymentIntentId
    ): PaymentIntentResult {
        try {
            // PayPal doesn't have a direct cancel endpoint for Orders API v2
            // Once an order is approved but not captured, it expires automatically after 3 hours
            // We just mark it as canceled in our system
            $this->logger->info('Cancelling PayPal order', [
                'order_id' => $gatewayPaymentIntentId,
            ]);

            // Get current order status
            $response = $this->client->getOrder($gatewayPaymentIntentId);

            $this->logger->info('PayPal order marked as cancelled', [
                'order_id' => $gatewayPaymentIntentId,
            ]);

            // Extract amount
            $amount = $this->extractAmountFromOrder($response);

            return PaymentIntentResult::success(
                gatewayPaymentIntentId: $gatewayPaymentIntentId,
                status: 'canceled',
                amount: $amount,
                clientSecret: null,
                customerId: null,
                rawResponse: json_encode($response) ?: null
            );
        } catch (\Exception $e) {
            $this->logger->error('PayPal order cancellation failed', [
                'error' => $e->getMessage(),
                'order_id' => $gatewayPaymentIntentId,
            ]);

            throw new PaymentGatewayException(sprintf('Failed to cancel PayPal order: %s', $e->getMessage()), 'paypal_cancel_failed', $e->getMessage(), null, $e);
        }
    }

    public function createRefund(
        string $gatewayPaymentIntentId,
        Money $amount,
        string $reason,
        string $idempotencyKey
    ): RefundResult {
        try {
            // Convert domain Money to PayPal format
            $amountValue = number_format($amount->getAmount() / 100, 2, '.', '');
            $currency = $amount->getCurrency()->getCurrencyCode();

            // Build refund payload
            $refundData = [
                'amount' => [
                    'value' => $amountValue,
                    'currency_code' => strtoupper($currency),
                ],
                'note_to_payer' => $reason,
                'invoice_id' => $idempotencyKey, // For idempotency
            ];

            $this->logger->info('Creating PayPal refund', [
                'capture_id' => $gatewayPaymentIntentId,
                'amount' => $amountValue,
                'reason' => $reason,
            ]);

            // PayPal requires the capture ID (not the order ID) for refunds
            // This should be stored in clientSecret from capture response
            $response = $this->client->createRefund($gatewayPaymentIntentId, $refundData);

            $refundId = $response['id'] ?? null;
            $status = $response['status'] ?? 'UNKNOWN';

            if (null === $refundId) {
                throw new PaymentGatewayException('PayPal refund creation failed: Missing refund ID', 'paypal_refund_creation_failed');
            }

            $this->logger->info('PayPal refund created successfully', [
                'refund_id' => $refundId,
                'status' => $status,
            ]);

            // Map PayPal refund status to domain
            $domainStatus = $this->mapPayPalRefundStatusToDomain($status);

            return RefundResult::success(
                gatewayRefundId: $refundId,
                status: $domainStatus,
                amount: $amount,
                rawResponse: json_encode($response) ?: null
            );
        } catch (\Exception $e) {
            $this->logger->error('PayPal refund creation failed', [
                'error' => $e->getMessage(),
                'capture_id' => $gatewayPaymentIntentId,
            ]);

            return RefundResult::failure(
                errorCode: 'refund_failed',
                errorMessage: $e->getMessage(),
                rawResponse: null
            );
        }
    }

    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        try {
            // PayPal webhook verification requires headers
            // Extract headers from signature (format: header1=value1;header2=value2)
            $headers = [];
            $headerPairs = explode(';', $signature);
            foreach ($headerPairs as $pair) {
                [$key, $value] = explode('=', $pair, 2);
                $headers[strtoupper($key)] = $value;
            }

            return $this->client->verifyWebhookSignature(
                $secret, // webhook_id
                $headers,
                $payload
            );
        } catch (\Exception $e) {
            $this->logger->error('PayPal webhook verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getGatewayId(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'PayPal';
    }

    /**
     * Map PayPal order status to domain payment intent status.
     */
    private function mapPayPalStatusToDomain(string $paypalStatus): string
    {
        return match ($paypalStatus) {
            'CREATED' => 'requires_payment_method', // Awaiting customer approval
            'SAVED' => 'requires_payment_method', // Draft order
            'APPROVED' => 'requires_capture', // Authorized, ready to capture
            'COMPLETED' => 'succeeded', // Funds captured
            'VOIDED' => 'canceled', // Authorization voided
            'PAYER_ACTION_REQUIRED' => 'requires_action', // 3D Secure, etc.
            default => 'processing', // Unknown status, assume processing
        };
    }

    /**
     * Map PayPal refund status to domain refund status.
     */
    private function mapPayPalRefundStatusToDomain(string $paypalStatus): string
    {
        return match ($paypalStatus) {
            'COMPLETED' => 'succeeded',
            'PENDING' => 'pending',
            'FAILED' => 'failed',
            'CANCELLED' => 'canceled',
            default => 'pending',
        };
    }

    /**
     * Extract approval URL from PayPal order response.
     *
     * @param array<string, mixed> $response
     */
    private function extractApprovalUrl(array $response): ?string
    {
        $links = $response['links'] ?? [];
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return $link['href'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extract amount from PayPal order response.
     *
     * @param array<string, mixed> $response
     */
    private function extractAmountFromOrder(array $response): Money
    {
        $purchaseUnits = $response['purchase_units'] ?? [];
        if (empty($purchaseUnits)) {
            return Money::fromScalars(0, 'USD');
        }

        $amount = $purchaseUnits[0]['amount'] ?? [];
        $value = (float) ($amount['value'] ?? 0);
        $currency = $amount['currency_code'] ?? 'USD';

        // Convert dollars to cents
        $amountInCents = (int) round($value * 100);

        return Money::fromScalars($amountInCents, $currency);
    }

    /**
     * Extract capture ID from PayPal capture response.
     *
     * @param array<string, mixed> $response
     */
    private function extractCaptureId(array $response): ?string
    {
        $purchaseUnits = $response['purchase_units'] ?? [];
        if (empty($purchaseUnits)) {
            return null;
        }

        $captures = $purchaseUnits[0]['payments']['captures'] ?? [];
        if (empty($captures)) {
            return null;
        }

        return $captures[0]['id'] ?? null;
    }
}
