<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Webhook;

use App\Payment\Application\Command\CapturePayment;
use App\Payment\Application\Query\GetPaymentById;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Stripe Webhook Handler.
 *
 * Procesează evenimente asincrone de la Stripe:
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 * - payment_intent.canceled
 * - charge.refunded
 */
final readonly class StripeWebhookHandler
{
    public function __construct(
        private string $webhookSecret,
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Procesează webhook event de la Stripe.
     *
     * @throws \RuntimeException dacă signature verification eșuează
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('stripe-signature');

        if (!$signature) {
            $this->logger->error('Stripe webhook: Missing signature header');

            return new Response('Missing signature', Response::HTTP_BAD_REQUEST);
        }

        try {
            // Verifică signature pentru securitate
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );

            $this->logger->info('Stripe webhook received', [
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);

            // Procesează evenimentul bazat pe tip
            $result = match ($event->type) {
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
                'payment_intent.canceled' => $this->handlePaymentIntentCanceled($event),
                'charge.refunded' => $this->handleChargeRefunded($event),
                default => $this->handleUnknownEvent($event),
            };

            return new Response($result, Response::HTTP_OK);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Stripe webhook: Invalid signature', [
                'error' => $e->getMessage(),
            ]);

            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Stripe webhook: Processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new Response('Webhook processing failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PaymentIntent succeeded - payment captured successfully.
     */
    private function handlePaymentIntentSucceeded(Event $event): string
    {
        /** @var PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;

        $this->logger->info('Stripe webhook: PaymentIntent succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
            'status' => $paymentIntent->status,
        ]);

        // Extragem payment_id din metadata
        $paymentId = $paymentIntent->metadata->payment_id ?? null;
        $tenantId = $paymentIntent->metadata->tenant_id ?? null;

        if (!$paymentId || !$tenantId) {
            $this->logger->warning('Stripe webhook: Missing payment_id or tenant_id in metadata', [
                'payment_intent_id' => $paymentIntent->id,
                'metadata' => $paymentIntent->metadata->toArray(),
            ]);

            return 'Missing metadata';
        }

        try {
            // Verificăm dacă payment există
            $query = new GetPaymentById(
                id: PaymentId::fromString($paymentId),
                tenantId: TenantId::fromString($tenantId)
            );

            $envelope = $this->queryBus->dispatch($query);
            $paymentDTO = $envelope->last(HandledStamp::class)?->getResult();

            if (!$paymentDTO) {
                $this->logger->warning('Stripe webhook: Payment not found', [
                    'payment_id' => $paymentId,
                ]);

                return 'Payment not found';
            }

            // Dacă payment-ul nu este deja captured, îl capturam
            if ('captured' !== $paymentDTO->status) {
                $this->logger->info('Stripe webhook: Capturing payment', [
                    'payment_id' => $paymentId,
                    'current_status' => $paymentDTO->status,
                ]);

                $captureCommand = new CapturePayment(
                    id: PaymentId::fromString($paymentId),
                    tenantId: TenantId::fromString($tenantId)
                );

                $this->commandBus->dispatch($captureCommand);

                $this->logger->info('Stripe webhook: Payment captured successfully', [
                    'payment_id' => $paymentId,
                ]);
            } else {
                $this->logger->info('Stripe webhook: Payment already captured', [
                    'payment_id' => $paymentId,
                ]);
            }

            return 'Payment processed';
        } catch (\Exception $e) {
            $this->logger->error('Stripe webhook: Failed to process payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * PaymentIntent failed - payment could not be completed.
     */
    private function handlePaymentIntentFailed(Event $event): string
    {
        /** @var PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;

        $this->logger->warning('Stripe webhook: PaymentIntent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
            'last_payment_error' => $paymentIntent->last_payment_error?->message,
        ]);

        $paymentId = $paymentIntent->metadata->payment_id ?? null;

        if ($paymentId) {
            $this->logger->info('Stripe webhook: Marking payment as failed', [
                'payment_id' => $paymentId,
                'error' => $paymentIntent->last_payment_error?->message,
            ]);

            // În viitor, vom avea un command UpdatePaymentStatus pentru a marca ca failed
            // Pentru moment, doar logăm
        }

        return 'Payment failure recorded';
    }

    /**
     * PaymentIntent canceled - payment was canceled.
     */
    private function handlePaymentIntentCanceled(Event $event): string
    {
        /** @var PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;

        $this->logger->info('Stripe webhook: PaymentIntent canceled', [
            'payment_intent_id' => $paymentIntent->id,
            'cancellation_reason' => $paymentIntent->cancellation_reason,
        ]);

        $paymentId = $paymentIntent->metadata->payment_id ?? null;

        if ($paymentId) {
            $this->logger->info('Stripe webhook: Payment canceled', [
                'payment_id' => $paymentId,
                'reason' => $paymentIntent->cancellation_reason,
            ]);

            // Payment-ul este deja marcat ca cancelled din command handler
            // Acest webhook este doar o confirmare
        }

        return 'Cancellation confirmed';
    }

    /**
     * Charge refunded - refund was processed.
     */
    private function handleChargeRefunded(Event $event): string
    {
        $charge = $event->data->object;

        $this->logger->info('Stripe webhook: Charge refunded', [
            'charge_id' => $charge->id,
            'amount_refunded' => $charge->amount_refunded,
            'refunded' => $charge->refunded,
        ]);

        // Refund-ul este deja procesat prin command handler
        // Acest webhook este doar o confirmare

        return 'Refund confirmed';
    }

    /**
     * Unknown event type - just log it.
     */
    private function handleUnknownEvent(Event $event): string
    {
        $this->logger->info('Stripe webhook: Unknown event type', [
            'event_id' => $event->id,
            'event_type' => $event->type,
        ]);

        return 'Event received';
    }
}
