<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Controller;

use App\Payment\Infrastructure\Webhook\StripeWebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Stripe Webhook Controller.
 *
 * Endpoint public pentru primirea webhook-urilor de la Stripe.
 * NU NECESITĂ autentificare JWT (Stripe trimite evenimente direct).
 *
 * Security:
 * - Signature verification via StripeWebhookHandler
 * - No authentication required (uses webhook secret)
 * - Returns 200 within 5 seconds to prevent retries
 *
 * Events handled:
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 * - payment_intent.canceled
 * - charge.refunded
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeWebhookHandler $webhookHandler
    ) {
    }

    /**
     * Primește și procesează webhook events de la Stripe.
     *
     * URL: POST /api/v1/webhooks/stripe
     * Headers:
     *   - stripe-signature: Required for signature verification
     *   - Content-Type: application/json
     *
     * Returns 200 even on processing errors to prevent Stripe retries.
     */
    #[Route('/api/v1/webhooks/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        return $this->webhookHandler->handle($request);
    }
}
