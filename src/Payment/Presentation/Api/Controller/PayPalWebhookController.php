<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Payment\Infrastructure\Webhook\PayPalWebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * PayPal Webhook Controller.
 *
 * Endpoint public pentru primirea webhook-urilor de la PayPal.
 * NU NECESITĂ autentificare JWT (PayPal trimite evenimente direct).
 */
final class PayPalWebhookController extends AbstractController
{
    public function __construct(
        private readonly PayPalWebhookHandler $webhookHandler
    ) {
    }

    /**
     * Primește și procesează webhook events de la PayPal.
     *
     * @Route("/api/webhooks/paypal", name="paypal_webhook", methods={"POST"})
     */
    #[Route('/api/webhooks/paypal', name: 'paypal_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        return $this->webhookHandler->handle($request);
    }
}
