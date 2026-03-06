<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Shared\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stripe payment controller for checkout flow.
 *
 * NOTE: Payment state transitions (authorize, capture, order status updates) are
 * handled exclusively by StripeWebhookController via verified webhook events.
 * This controller only handles payment intent creation and verification.
 *
 * Security:
 * - Rate limited: 10 requests/minute per IP (api_payment limiter)
 * - Input validation: amount ceiling, email format, UUID format
 * - Error sanitization: generic error messages in responses, details logged server-side
 */
#[Route('/api/v1/payments/stripe', name: 'api_stripe_payment_')]
class StripePaymentController extends AbstractController
{
    private const MAX_AMOUNT_CENTS = 99_999_99; // $999,999.99

    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly MessageBusInterface $commandBus,
        #[Autowire(service: 'limiter.api_payment')]
        private readonly RateLimiterFactory $apiPaymentLimiter,
        private readonly LoggerInterface $logger,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    #[Route('/create-intent', name: 'create_intent', methods: ['POST'])]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        // Rate limiting
        $limiter = $this->apiPaymentLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many payment requests. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            $amount = $data['amount'] ?? null;
            $currency = $data['currency'] ?? 'usd';
            $customerEmail = $data['customerEmail'] ?? null;
            $orderId = $data['orderId'] ?? null;
            $tenantId = $request->headers->get('X-Tenant-ID');

            // Validation — amount
            if (null === $amount || !is_numeric($amount) || (int) $amount <= 0) {
                return new JsonResponse(['error' => 'Invalid amount'], Response::HTTP_BAD_REQUEST);
            }

            if ((int) $amount > self::MAX_AMOUNT_CENTS) {
                return new JsonResponse(['error' => 'Amount exceeds maximum allowed'], Response::HTTP_BAD_REQUEST);
            }

            // Validation — orderId
            if (!is_string($orderId) || '' === $orderId) {
                return new JsonResponse(['error' => 'orderId is required'], Response::HTTP_BAD_REQUEST);
            }

            // Validation — tenantId (UUID format)
            if (!is_string($tenantId) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenantId)) {
                return new JsonResponse(['error' => 'Valid X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
            }

            // Validation — email format
            if (!is_string($customerEmail) || false === filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['error' => 'Valid customerEmail is required'], Response::HTTP_BAD_REQUEST);
            }

            // Create payment ID
            $paymentId = \App\Payment\Domain\ValueObject\PaymentId::generate();

            // Dispatch InitiatePayment command
            $command = new \App\Payment\Application\Command\InitiatePayment(
                paymentId: $paymentId,
                tenantId: \App\Shared\Domain\ValueObject\TenantId::fromString($tenantId),
                orderId: $orderId,
                amount: Money::fromScalars((int) $amount, strtoupper($currency)),
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
            $this->logger->error('Stripe payment intent creation failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'error' => 'Payment processing failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/verify-payment/{paymentIntentId}', name: 'verify_payment', methods: ['GET'])]
    public function verifyPayment(Request $request, string $paymentIntentId): JsonResponse
    {
        // Validate paymentIntentId format (Stripe format: pi_*)
        if (!preg_match('/^pi_[a-zA-Z0-9]{16,}$/', $paymentIntentId)) {
            return new JsonResponse(['error' => 'Invalid payment intent ID format'], Response::HTTP_BAD_REQUEST);
        }

        // Rate limiting
        $limiter = $this->apiPaymentLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many requests. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            return new JsonResponse([
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'verified' => 'succeeded' === $paymentIntent->status,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Stripe payment verification failed', [
                'paymentIntentId' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Payment verification failed.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
