<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * 2Checkout Payment Controller.
 *
 * Handles 2Checkout payment operations via REST API.
 */
#[Route('/api/v1/payments/2checkout', name: 'api_payment_2checkout_')]
final class TwoCheckoutPaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create 2Checkout Order.
     *
     * Creates a 2Checkout order and returns the reference number.
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
            $customerEmail = $data['customerEmail'] ?? 'customer@example.com';
            $billingDetails = $data['billing'] ?? [];

            $this->logger->info('2Checkout: Creating order', [
                'amount' => $amount,
                'currency' => $currency,
                'customer_email' => $customerEmail,
            ]);

            // Get 2Checkout gateway
            $gateway = $this->gatewayFactory->getGateway(\App\Payment\Domain\ValueObject\PaymentGateway::twoCheckout());

            // Create 2Checkout order
            $result = $gateway->authorize(
                $amount,
                $currency,
                \App\Payment\Domain\ValueObject\PaymentMethod::card(),
                [
                    'customer_email' => $customerEmail,
                    'order_id' => $data['orderId'] ?? null,
                    'billing' => array_merge([
                        'first_name' => 'Test',
                        'last_name' => 'Customer',
                        'email' => $customerEmail,
                        'country' => 'US',
                        'state' => 'CA',
                        'city' => 'Los Angeles',
                        'address' => '123 Test St',
                        'zip' => '90001',
                    ], $billingDetails),
                    'customer_ip' => $request->getClientIp() ?? '127.0.0.1',
                    'description' => $data['description'] ?? 'Order payment',
                ]
            );

            $this->logger->info('2Checkout: Order created successfully', [
                'reference_no' => $result['transaction_id'],
                'order_no' => $result['metadata']['order_no'] ?? null,
            ]);

            return new JsonResponse([
                'referenceNo' => $result['transaction_id'],
                'orderNo' => $result['metadata']['order_no'] ?? null,
                'approvalUrl' => $result['metadata']['approval_url'] ?? null,
                'status' => $result['status'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to create 2Checkout order: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get 2Checkout Order Status.
     *
     * Retrieves the status of a 2Checkout order.
     */
    #[Route('/order-status/{referenceNo}', name: 'order_status', methods: ['GET'])]
    public function getOrderStatus(string $referenceNo): JsonResponse
    {
        try {
            $this->logger->info('2Checkout: Fetching order status', [
                'reference_no' => $referenceNo,
            ]);

            // Get 2Checkout gateway
            $gateway = $this->gatewayFactory->getGateway(\App\Payment\Domain\ValueObject\PaymentGateway::twoCheckout());

            // Get order status
            $result = $gateway->getStatus($referenceNo);

            return new JsonResponse([
                'referenceNo' => $referenceNo,
                'status' => $result['status'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'metadata' => $result['metadata'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Failed to fetch order status', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to fetch 2Checkout order status: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Complete 2Checkout Order.
     *
     * Marks a 2Checkout order as completed after successful payment.
     * Note: 2Checkout auto-captures, so this is mainly for verification.
     */
    #[Route('/complete-order', name: 'complete_order', methods: ['POST'])]
    public function completeOrder(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['referenceNo'])) {
                return new JsonResponse([
                    'error' => 'Missing required field: referenceNo',
                ], Response::HTTP_BAD_REQUEST);
            }

            $referenceNo = $data['referenceNo'];

            $this->logger->info('2Checkout: Completing order', [
                'reference_no' => $referenceNo,
            ]);

            // Get 2Checkout gateway
            $gateway = $this->gatewayFactory->getGateway(\App\Payment\Domain\ValueObject\PaymentGateway::twoCheckout());

            // Capture (verify) the order
            $result = $gateway->capture($referenceNo);

            $this->logger->info('2Checkout: Order completed successfully', [
                'reference_no' => $referenceNo,
                'status' => $result['status'],
            ]);

            return new JsonResponse([
                'referenceNo' => $referenceNo,
                'status' => $result['status'],
                'capturedAmount' => $result['captured_amount'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Order completion failed', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to complete 2Checkout order: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
