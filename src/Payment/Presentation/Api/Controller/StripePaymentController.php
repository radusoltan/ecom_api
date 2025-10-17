<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/payments/stripe', name: 'api_stripe_payment_')]
class StripePaymentController extends AbstractController
{
    public function __construct(
        private readonly string $stripeSecretKey,
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

            if ($amount === null || $amount <= 0) {
                return new JsonResponse([
                    'error' => 'Invalid amount'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) $amount,
                'currency' => strtolower($currency),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'receipt_email' => $customerEmail,
                'metadata' => [
                    'tenant_id' => $request->headers->get('X-Tenant-ID'),
                ],
            ]);

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
