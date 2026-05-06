<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Payment\Application\Command\CapturePayment;
use App\Payment\Application\Command\InitiatePayment;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PayPal Payment Controller.
 *
 * Handles PayPal payment operations via REST API.
 * Follows the same command bus pattern as StripePaymentController.
 *
 * Security:
 * - Rate limited: 10 requests/minute per IP (api_payment limiter)
 * - Input validation: amount ceiling, email format, UUID format
 * - Error sanitization: generic error messages in responses, details logged server-side
 */
#[Route('/api/v1/payments/paypal', name: 'api_payment_paypal_')]
final class PayPalPaymentController extends AbstractController
{
    private const MAX_AMOUNT_CENTS = 99_999_99; // $999,999.99

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        #[Autowire(service: 'limiter.api_payment')]
        private readonly RateLimiterFactory $apiPaymentLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create PayPal Order.
     *
     * Creates a PayPal order via InitiatePayment command and returns
     * the payment ID, PayPal order ID, and approval URL.
     */
    #[Route('/create-order', name: 'create_order', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
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
            $currency = $data['currency'] ?? 'USD';
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
            $paymentId = PaymentId::generate();

            // Dispatch InitiatePayment command
            $command = new InitiatePayment(
                paymentId: $paymentId,
                tenantId: TenantId::fromString($tenantId),
                orderId: $orderId,
                amount: Money::fromScalars((int) $amount, strtoupper($currency)),
                customerEmail: $customerEmail,
                method: PaymentMethod::paypal(),
                gateway: PaymentGateway::paypal()
            );

            $result = $this->commandBus->dispatch($command);

            // Extract result from envelope
            $handlerResult = $result->last(HandledStamp::class)?->getResult();

            if (!$handlerResult) {
                throw new \RuntimeException('Payment initiation failed');
            }

            return new JsonResponse([
                'paymentId' => $handlerResult['paymentId'],
                'orderId' => $handlerResult['paymentIntentId'],
                'approvalUrl' => $handlerResult['clientSecret'],
                'status' => $handlerResult['status'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('PayPal order creation failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'error' => 'Payment processing failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Capture PayPal Order.
     *
     * Captures a previously authorized PayPal order via CapturePayment command.
     * Called after customer approves the payment on PayPal and returns.
     */
    #[Route('/capture-order', name: 'capture_order', methods: ['POST'])]
    public function captureOrder(Request $request): JsonResponse
    {
        // Rate limiting
        $limiter = $this->apiPaymentLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many requests. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            $paymentId = $data['paymentId'] ?? null;

            if (!is_string($paymentId) || '' === $paymentId) {
                return new JsonResponse(['error' => 'paymentId is required'], Response::HTTP_BAD_REQUEST);
            }

            // Validate UUID format
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $paymentId)) {
                return new JsonResponse(['error' => 'Invalid paymentId format'], Response::HTTP_BAD_REQUEST);
            }

            $this->logger->info('PayPal: Capturing order', [
                'payment_id' => $paymentId,
            ]);

            // Dispatch CapturePayment command
            $command = new CapturePayment(
                id: PaymentId::fromString($paymentId),
            );

            $this->commandBus->dispatch($command);

            return new JsonResponse([
                'paymentId' => $paymentId,
                'status' => 'captured',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('PayPal order capture failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'error' => 'Payment capture failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
