<?php

declare(strict_types=1);

namespace App\Payment\Domain\Service;

use App\Payment\Domain\Exception\PaymentGatewayException;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;

/**
 * Payment Gateway Port - defines the contract for payment processing adapters.
 *
 * This interface follows the Hexagonal Architecture pattern (Port/Adapter):
 * - Port: This interface (in Domain layer)
 * - Adapters: StripeGateway, PayPalGateway (in Infrastructure layer)
 *
 * Design Principles:
 * - NO framework-specific types (no Stripe/PayPal classes in signature)
 * - Immutable result DTOs (PaymentIntentResult, RefundResult)
 * - Rich domain exceptions for error handling
 * - Supports both authorize-then-capture and direct capture flows
 *
 * Payment Flow Patterns:
 * 1. Authorize-then-Capture (recommended for physical goods):
 *    - createPaymentIntent() -> authorize funds
 *    - confirmPaymentIntent() -> complete authorization (if 3DS required)
 *    - capturePaymentIntent() -> charge customer (on shipment)
 *    - cancelPaymentIntent() -> release funds (if order cancelled before shipment)
 *
 * 2. Direct Capture (digital goods, services):
 *    - createPaymentIntent() with auto-capture enabled
 *    - confirmPaymentIntent() -> charge immediately
 *
 * Implementations: StripeGateway, PayPalGateway, MockGateway (for testing)
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment intent for authorization.
     * Does NOT charge the customer yet - only reserves funds.
     *
     * Use Case: Start checkout process, create order, reserve payment
     *
     * @param PaymentId            $paymentId      Domain payment ID for idempotency
     * @param Money                $amount         Amount to authorize
     * @param string               $currency       ISO 4217 currency code (USD, EUR, GBP)
     * @param string               $idempotencyKey Unique key to prevent duplicate charges
     * @param string|null          $customerId     Gateway customer ID (for saved payment methods)
     * @param array<string, mixed> $metadata       Additional data (order_id, tenant_id, etc.)
     *
     * @return PaymentIntentResult Result with gateway payment intent ID and client secret
     *
     * @throws PaymentGatewayException If intent creation fails
     */
    public function createPaymentIntent(
        PaymentId $paymentId,
        Money $amount,
        string $currency,
        string $idempotencyKey,
        ?string $customerId = null,
        array $metadata = []
    ): PaymentIntentResult;

    /**
     * Confirm a payment intent (complete authorization).
     * Called after customer provides payment details (card, 3DS, etc.)
     *
     * Use Case: Customer submitted payment form, complete authorization
     *
     * @param string $gatewayPaymentIntentId Gateway's payment intent ID
     * @param string $paymentMethodId        Gateway payment method ID (card token, PayPal token)
     *
     * @return PaymentIntentResult Result with status (may require additional action for 3DS)
     *
     * @throws PaymentGatewayException If confirmation fails
     */
    public function confirmPaymentIntent(
        string $gatewayPaymentIntentId,
        string $paymentMethodId
    ): PaymentIntentResult;

    /**
     * Capture an authorized payment.
     * Actually charges the customer - money is transferred.
     *
     * Use Case: Order shipped, capture reserved funds
     *
     * @param string     $gatewayPaymentIntentId Gateway's payment intent ID
     * @param Money|null $amount                 Amount to capture (null = full authorized amount)
     *
     * @return PaymentIntentResult Result with captured amount and status
     *
     * @throws PaymentGatewayException If capture fails
     */
    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null
    ): PaymentIntentResult;

    /**
     * Cancel/void an authorized but not captured payment.
     * Releases reserved funds back to customer.
     *
     * Use Case: Order cancelled before shipment, release authorization
     *
     * @param string $gatewayPaymentIntentId Gateway's payment intent ID
     *
     * @return PaymentIntentResult Result with cancelled status
     *
     * @throws PaymentGatewayException If cancellation fails
     */
    public function cancelPaymentIntent(
        string $gatewayPaymentIntentId
    ): PaymentIntentResult;

    /**
     * Create a refund for a captured payment.
     * Returns money to customer's original payment method.
     *
     * Use Case: Order returned, refund customer
     *
     * @param string $gatewayPaymentIntentId Gateway's payment intent ID (from capture)
     * @param Money  $amount                 Amount to refund (supports partial refunds)
     * @param string $reason                 Refund reason for audit trail
     * @param string $idempotencyKey         Unique key to prevent duplicate refunds
     *
     * @return RefundResult Result with refund ID and status
     *
     * @throws PaymentGatewayException If refund fails
     */
    public function createRefund(
        string $gatewayPaymentIntentId,
        Money $amount,
        string $reason,
        string $idempotencyKey
    ): RefundResult;

    /**
     * Verify webhook signature for security.
     * Ensures webhook came from the actual payment gateway (prevents spoofing).
     *
     * Use Case: Webhook received, verify authenticity before processing
     *
     * @param string $payload   Raw webhook payload (JSON string)
     * @param string $signature Signature from webhook header (Stripe-Signature, etc.)
     * @param string $secret    Webhook secret configured in gateway
     *
     * @return bool True if signature is valid
     *
     * @throws PaymentGatewayException If verification fails
     */
    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool;

    /**
     * Get the gateway identifier (stripe, paypal, etc.)
     *
     * @return PaymentMethod Gateway identifier as domain value object
     */
    public function getGatewayId(): PaymentMethod;

    /**
     * Get the gateway name for display/logging.
     *
     * @return string Human-readable gateway name (e.g., "Stripe", "PayPal")
     */
    public function getName(): string;
}
