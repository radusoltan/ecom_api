<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * 2Checkout Payment Controller.
 *
 * Status: PLANNED — 2Checkout/Verifone integration is scheduled for a future release.
 * All endpoints return 501 Not Implemented until the gateway adapter is completed.
 */
#[Route('/api/v1/payments/2checkout', name: 'api_payment_2checkout_')]
final class TwoCheckoutPaymentController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/create-order', name: 'create_order', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        return $this->notImplementedResponse('create-order');
    }

    #[Route('/order-status/{referenceNo}', name: 'order_status', methods: ['GET'])]
    public function getOrderStatus(string $referenceNo): JsonResponse
    {
        return $this->notImplementedResponse('order-status');
    }

    #[Route('/complete-order', name: 'complete_order', methods: ['POST'])]
    public function completeOrder(Request $request): JsonResponse
    {
        return $this->notImplementedResponse('complete-order');
    }

    private function notImplementedResponse(string $operation): JsonResponse
    {
        $this->logger->info('2Checkout: Operation not yet available', [
            'operation' => $operation,
            'status' => 'planned',
        ]);

        return new JsonResponse([
            'error' => '2Checkout payment gateway is not yet available.',
            'detail' => 'This feature is planned for a future release. Use Stripe or PayPal instead.',
            'status' => 'planned',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
