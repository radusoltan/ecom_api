<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stripe payment controller for checkout flow.
 *
 * NOTE: Payment state transitions (authorize, capture, order status updates) are
 * handled exclusively by StripeWebhookController via verified webhook events.
 * This controller only handles payment intent creation and verification.
 */
#[Route('/api/v1/payments/stripe', name: 'api_stripe_payment_')]
class StripePaymentController extends AbstractController
{
    public function __construct(
        private readonly string $stripeSecretKey,
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
            $orderId = $data['orderId'] ?? null;
            $tenantId = $request->headers->get('X-Tenant-ID');

            // Validation
            if (null === $amount || $amount <= 0) {
                return new JsonResponse([
                    'error' => 'Invalid amount',
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$orderId) {
                return new JsonResponse([
                    'error' => 'orderId is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$tenantId) {
                return new JsonResponse([
                    'error' => 'X-Tenant-ID header is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$customerEmail) {
                return new JsonResponse([
                    'error' => 'customerEmail is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create payment ID
            $paymentId = \App\Payment\Domain\ValueObject\PaymentId::generate();

            // Dispatch InitiatePayment command
            $command = new \App\Payment\Application\Command\InitiatePayment(
                paymentId: $paymentId,
                tenantId: \App\Shared\Domain\ValueObject\TenantId::fromString($tenantId),
                orderId: $orderId,
                amountInCents: (int) $amount,
                currency: strtoupper($currency),
                customerEmail: $customerEmail,
                method: \App\Payment\Domain\ValueObject\PaymentMethod::card(),
                gateway: \App\Payment\Domain\ValueObject\PaymentGateway::stripe()
            );

            $result = $this->commandBus->dispatch($command);

            // Extract result from envelope
            $handlerResult = $result->last(\Symfony\Component\Messenger\Stamp\HandledStamp::class)?->getResult();

            if (!$handlerResult) {
                throw new \RuntimeException('Payment initiation failed');
            }

            return new JsonResponse([
                'clientSecret' => $handlerResult['clientSecret'],
                'paymentIntentId' => $handlerResult['paymentIntentId'],
                'paymentId' => $handlerResult['paymentId'],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
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
                'verified' => 'succeeded' === $paymentIntent->status,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
