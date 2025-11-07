<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * PayPal Payment Controller.
 *
 * Handles PayPal payment operations via REST API.
 */
#[Route('/api/v1/payments/paypal', name: 'api_payment_paypal_')]
final class PayPalPaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create PayPal Order.
     *
     * Creates a PayPal order and returns the order ID and approval URL.
     */
    #[Route('/create-order', name: 'create_order', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['amount'], $data['currency'])) {
                return new JsonResponse([
                    'error' => 'Missing required fields: amount, currency',
                ], Response::HTTP_BAD_REQUEST);
            }

            $amount = (int) $data['amount']; // Amount in cents
            $currency = strtoupper($data['currency']);
            $customerEmail = $data['customerEmail'] ?? null;

            $this->logger->info('PayPal: Creating order', [
                'amount' => $amount,
                'currency' => $currency,
                'customer_email' => $customerEmail,
            ]);

            // Get PayPal gateway
            $gateway = $this->gatewayFactory->getGateway(\App\Payment\Domain\ValueObject\PaymentGateway::paypal());

            // Create PayPal order (authorize)
            $result = $gateway->authorize(
                $amount,
                $currency,
                \App\Payment\Domain\ValueObject\PaymentMethod::paypal(),
                [
                    'customer_email' => $customerEmail,
                    'order_id' => $data['orderId'] ?? null,
                ]
            );

            $this->logger->info('PayPal: Order created successfully', [
                'paypal_order_id' => $result['transaction_id'],
                'approval_url' => $result['metadata']['approval_url'] ?? null,
            ]);

            return new JsonResponse([
                'orderId' => $result['transaction_id'],
                'approvalUrl' => $result['metadata']['approval_url'] ?? null,
                'status' => $result['status'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to create PayPal order: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Capture PayPal Order.
     *
     * Captures a previously authorized PayPal order.
     */
    #[Route('/capture-order', name: 'capture_order', methods: ['POST'])]
    public function captureOrder(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['orderId'])) {
                return new JsonResponse([
                    'error' => 'Missing required field: orderId',
                ], Response::HTTP_BAD_REQUEST);
            }

            $orderId = $data['orderId'];

            $this->logger->info('PayPal: Capturing order', [
                'paypal_order_id' => $orderId,
            ]);

            // Get PayPal gateway
            $gateway = $this->gatewayFactory->getGateway(\App\Payment\Domain\ValueObject\PaymentGateway::paypal());

            // Capture PayPal order
            $result = $gateway->capture($orderId);

            $this->logger->info('PayPal: Order captured successfully', [
                'paypal_order_id' => $orderId,
                'capture_id' => $result['transaction_id'],
                'status' => $result['status'],
            ]);

            return new JsonResponse([
                'captureId' => $result['transaction_id'],
                'status' => $result['status'],
                'capturedAmount' => $result['captured_amount'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Order capture failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to capture PayPal order: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get PayPal Order Status.
     *
     * Retrieves the status of a PayPal order.
     */
    #[Route('/order-status/{orderId}', name: 'order_status', methods: ['GET'])]
    public function getOrderStatus(string $orderId): JsonResponse
    {
        try {
            $this->logger->info('PayPal: Fetching order status', [
                'paypal_order_id' => $orderId,
            ]);

            // Get PayPal gateway
            $gateway = $this->gatewayFactory->getGateway(\App\Payment\Domain\ValueObject\PaymentGateway::paypal());

            // Get order status
            $result = $gateway->getStatus($orderId);

            return new JsonResponse([
                'orderId' => $orderId,
                'status' => $result['status'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'metadata' => $result['metadata'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Failed to fetch order status', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to fetch PayPal order status: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
