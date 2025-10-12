<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Payment\Infrastructure\Webhook\StripeWebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Stripe Webhook Controller
 *
 * Endpoint public pentru primirea webhook-urilor de la Stripe.
 * NU NECESITĂ autentificare JWT (Stripe trimite evenimente direct).
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeWebhookHandler $webhookHandler
    ) {
    }

    /**
     * Primește și procesează webhook events de la Stripe
     *
     * @Route("/api/webhooks/stripe", name="stripe_webhook", methods={"POST"})
     */
    #[Route('/api/webhooks/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        return $this->webhookHandler->handle($request);
    }
}
