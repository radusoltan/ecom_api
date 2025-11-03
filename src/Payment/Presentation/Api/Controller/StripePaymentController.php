<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Order\Application\Command\UpdateOrderStatusCommand;
use App\Payment\Application\Command\CapturePayment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/payments/stripe', name: 'api_stripe_payment_')]
class StripePaymentController extends AbstractController
{
    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    #[Route('/create-intent', name: 'create_intent', methods: ['POST'])]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $amount = $data['amount'] ?? null;
            $currency = $data['currency'] ?? 'usd';
            $customerEmail = $data['customerEmail'] ?? null;
            $paymentId = $data['paymentId'] ?? null;
            $orderId = $data['orderId'] ?? null;
            $tenantId = $request->headers->get('X-Tenant-ID');

            if ($amount === null || $amount <= 0) {
                return new JsonResponse([
                    'error' => 'Invalid amount'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create payment intent with metadata
            $metadata = [
                'tenant_id' => $tenantId,
            ];

            if ($paymentId) {
                $metadata['payment_id'] = $paymentId;
            }

            if ($orderId) {
                $metadata['order_id'] = $orderId;
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => (int) $amount,
                'currency' => strtolower($currency),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'receipt_email' => $customerEmail,
                'metadata' => $metadata,
            ]);

            // If paymentId is provided, authorize payment with gateway_transaction_id
            if ($paymentId && $tenantId) {
                try {
                    $payment = $this->paymentRepository->findById(
                        \App\Payment\Domain\ValueObject\PaymentId::fromString($paymentId),
                        \App\Shared\Domain\ValueObject\TenantId::fromString($tenantId)
                    );

                    if ($payment) {
                        // Authorize payment with gateway transaction ID
                        $payment->authorize($paymentIntent->id);
                        $this->paymentRepository->save($payment);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the request
                    error_log('Failed to authorize payment with gateway_transaction_id: ' . $e->getMessage());
                }
            }

            return new JsonResponse([
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/verify-payment/{paymentIntentId}', name: 'verify_payment', methods: ['GET'])]
    public function verifyPayment(string $paymentIntentId): JsonResponse
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            return new JsonResponse([
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'verified' => $paymentIntent->status === 'succeeded',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/capture-after-success/{paymentIntentId}', name: 'capture_after_success', methods: ['POST'])]
    public function captureAfterSuccess(string $paymentIntentId, Request $request): JsonResponse
    {
        try {
            // Retrieve the payment intent to verify it succeeded
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            // Find payment by gateway transaction ID
            $tenantId = $request->headers->get('X-Tenant-ID');
            if (!$tenantId) {
                return new JsonResponse([
                    'error' => 'X-Tenant-ID header is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            $payment = $this->paymentRepository->findByGatewayTransactionId($paymentIntentId, $tenantId);

            if (!$payment) {
                return new JsonResponse([
                    'error' => 'Payment not found for this transaction'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($paymentIntent->status === 'succeeded') {
                // Payment already succeeded in Stripe
                // For test mode with automatic_payment_methods, Stripe captures automatically
                // Just mark as captured in our system if not already captured
                if ($payment->status()->value() !== 'captured') {
                    // Directly mark payment as captured (skip gateway capture call)
                    $payment->capture();
                    $this->paymentRepository->save($payment);
                }

                // Update order status to paid
                $this->commandBus->dispatch(new UpdateOrderStatusCommand(
                    $payment->orderId(),
                    $tenantId,
                    'paid' // OrderStatus::PAID constant
                ));

                return new JsonResponse([
                    'success' => true,
                    'status' => $paymentIntent->status,
                    'message' => 'Payment captured and order updated successfully',
                ]);
            }

            // If payment requires capture, capture it
            if ($paymentIntent->status === 'requires_capture') {
                $paymentIntent = $paymentIntent->capture();
            }

            return new JsonResponse([
                'success' => $paymentIntent->status === 'succeeded',
                'status' => $paymentIntent->status,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/webhook', name: 'webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        // Verify webhook signature (webhook secret should be configured)
        // For now, just acknowledge receipt

        try {
            $event = json_decode($payload, true);

            // Handle different event types
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    // Handle successful payment
                    // TODO: Update order status, send confirmation email
                    break;

                case 'payment_intent.payment_failed':
                    // Handle failed payment
                    // TODO: Update order status, notify customer
                    break;
            }

            return new JsonResponse(['received' => true]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
