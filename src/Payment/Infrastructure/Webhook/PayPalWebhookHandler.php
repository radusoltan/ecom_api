<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Webhook;

use App\Payment\Application\Command\CapturePayment;
use App\Payment\Application\Command\MarkPaymentAsFailed;
use App\Payment\Application\Query\GetPaymentById;
use App\Payment\Domain\Model\PaymentId;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PayPal Webhook Handler.
 *
 * Procesează evenimente asincrone de la PayPal:
 * - PAYMENT.CAPTURE.COMPLETED - Payment captured successfully
 * - PAYMENT.CAPTURE.DENIED - Payment failed/denied
 * - PAYMENT.CAPTURE.REFUNDED - Refund processed
 *
 * PayPal Webhook Verification:
 *
 * @see https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature
 */
final readonly class PayPalWebhookHandler
{
    private const WEBHOOK_VERIFY_URL_SANDBOX = 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature';
    private const WEBHOOK_VERIFY_URL_PRODUCTION = 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature';

    private string $webhookVerifyUrl;

    public function __construct(
        private string $webhookId,
        private string $clientId,
        private string $clientSecret,
        private HttpClientInterface $httpClient,
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private LoggerInterface $logger,
        private bool $sandbox = true
    ) {
        $this->webhookVerifyUrl = $sandbox ? self::WEBHOOK_VERIFY_URL_SANDBOX : self::WEBHOOK_VERIFY_URL_PRODUCTION;
    }

    /**
     * Procesează webhook event de la PayPal.
     *
     * @throws \RuntimeException dacă signature verification eșuează
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $headers = [
            'transmission_id' => $request->headers->get('paypal-transmission-id'),
            'transmission_time' => $request->headers->get('paypal-transmission-time'),
            'transmission_sig' => $request->headers->get('paypal-transmission-sig'),
            'cert_url' => $request->headers->get('paypal-cert-url'),
            'auth_algo' => $request->headers->get('paypal-auth-algo'),
        ];

        // Validate required headers
        if (empty($headers['transmission_sig'])) {
            $this->logger->error('PayPal webhook: Missing transmission signature header');

            return new Response('Missing signature', Response::HTTP_BAD_REQUEST);
        }

        try {
            $eventData = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            // Verify webhook signature
            if (!$this->verifySignature($payload, $headers, $eventData)) {
                $this->logger->error('PayPal webhook: Invalid signature', [
                    'event_id' => $eventData['id'] ?? 'unknown',
                ]);

                return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
            }

            $eventType = $eventData['event_type'] ?? null;
            $eventId = $eventData['id'] ?? 'unknown';

            $this->logger->info('PayPal webhook received', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            // Procesează evenimentul bazat pe tip
            $result = match ($eventType) {
                'PAYMENT.AUTHORIZATION.CREATED' => $this->handleAuthorizationCreated($eventData),
                'PAYMENT.AUTHORIZATION.VOIDED' => $this->handleAuthorizationVoided($eventData),
                'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($eventData),
                'PAYMENT.CAPTURE.DENIED' => $this->handleCaptureDenied($eventData),
                'PAYMENT.CAPTURE.REFUNDED' => $this->handleCaptureRefunded($eventData),
                'CHECKOUT.ORDER.APPROVED' => $this->handleOrderApproved($eventData),
                default => $this->handleUnknownEvent($eventData),
            };

            return new Response($result, Response::HTTP_OK);
        } catch (\JsonException $e) {
            $this->logger->error('PayPal webhook: Invalid JSON payload', [
                'error' => $e->getMessage(),
            ]);

            return new Response('Invalid JSON', Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('PayPal webhook: Processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new Response('Webhook processing failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify PayPal webhook signature.
     *
     * @param array<string, string|null> $headers
     * @param array<string, mixed>       $eventData
     *
     * @see https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature
     */
    private function verifySignature(string $payload, array $headers, array $eventData): bool
    {
        try {
            // Get OAuth access token
            $accessToken = $this->getAccessToken();

            // Prepare verification request
            $verificationData = [
                'transmission_id' => $headers['transmission_id'],
                'transmission_time' => $headers['transmission_time'],
                'cert_url' => $headers['cert_url'],
                'auth_algo' => $headers['auth_algo'],
                'transmission_sig' => $headers['transmission_sig'],
                'webhook_id' => $this->webhookId,
                'webhook_event' => $eventData,
            ];

            $response = $this->httpClient->request('POST', $this->webhookVerifyUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $verificationData,
            ]);

            $result = $response->toArray();

            $verificationStatus = $result['verification_status'] ?? 'FAILURE';

            $this->logger->info('PayPal webhook signature verification', [
                'status' => $verificationStatus,
                'event_id' => $eventData['id'] ?? 'unknown',
            ]);

            return 'SUCCESS' === $verificationStatus;
        } catch (\Exception $e) {
            $this->logger->error('PayPal webhook: Signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get OAuth 2.0 access token from PayPal.
     */
    private function getAccessToken(): string
    {
        $apiBaseUrl = $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        try {
            $response = $this->httpClient->request('POST', "{$apiBaseUrl}/v1/oauth2/token", [
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'body' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = $response->toArray();

            return $data['access_token'];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to obtain PayPal access token: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * PAYMENT.CAPTURE.COMPLETED - Payment captured successfully.
     *
     * @param array<string, mixed> $eventData
     */
    private function handleCaptureCompleted(array $eventData): string
    {
        $resource = $eventData['resource'] ?? [];
        $captureId = $resource['id'] ?? null;
        $customId = $resource['custom_id'] ?? null; // Our payment_id

        $this->logger->info('PayPal webhook: Payment capture completed', [
            'capture_id' => $captureId,
            'custom_id' => $customId,
            'status' => $resource['status'] ?? null,
        ]);

        // Extract payment_id from custom_id or supplementary_data
        $paymentId = $resource['supplementary_data']['related_ids']['order_id'] ?? $customId;

        if (!$paymentId) {
            $this->logger->warning('PayPal webhook: Missing payment_id', [
                'capture_id' => $captureId,
                'resource' => $resource,
            ]);

            return 'Missing metadata';
        }

        try {
            // Verificăm dacă payment există
            $query = new GetPaymentById(
                id: PaymentId::fromString($paymentId)
            );

            $envelope = $this->queryBus->dispatch($query);
            $paymentDTO = $envelope->last(HandledStamp::class)?->getResult();

            if (!$paymentDTO) {
                $this->logger->warning('PayPal webhook: Payment not found', [
                    'payment_id' => $paymentId,
                ]);

                return 'Payment not found';
            }

            // Dacă payment-ul nu este deja captured, îl capturam
            if ('captured' !== $paymentDTO->status) {
                $this->logger->info('PayPal webhook: Capturing payment', [
                    'payment_id' => $paymentId,
                    'current_status' => $paymentDTO->status,
                ]);

                $captureCommand = new CapturePayment(
                    id: PaymentId::fromString($paymentId)
                );

                $this->commandBus->dispatch($captureCommand);

                $this->logger->info('PayPal webhook: Payment captured successfully', [
                    'payment_id' => $paymentId,
                ]);
            } else {
                $this->logger->info('PayPal webhook: Payment already captured', [
                    'payment_id' => $paymentId,
                ]);
            }

            return 'Payment processed';
        } catch (\Exception $e) {
            $this->logger->error('PayPal webhook: Failed to process payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * PAYMENT.CAPTURE.DENIED - Payment failed/denied.
     *
     * @param array<string, mixed> $eventData
     */
    private function handleCaptureDenied(array $eventData): string
    {
        $resource = $eventData['resource'] ?? [];
        $captureId = $resource['id'] ?? null;
        $statusDetails = $resource['status_details'] ?? [];
        $reason = $statusDetails['reason'] ?? 'unknown';

        $this->logger->warning('PayPal webhook: Payment capture denied', [
            'capture_id' => $captureId,
            'reason' => $reason,
            'status' => $resource['status'] ?? null,
        ]);

        // Extract payment_id from custom_id or supplementary_data
        $customId = $resource['custom_id'] ?? null;
        $paymentId = $resource['supplementary_data']['related_ids']['order_id'] ?? $customId;

        if (!$paymentId) {
            $this->logger->warning('PayPal webhook: Missing payment_id for failed payment', [
                'capture_id' => $captureId,
            ]);

            return 'Missing payment_id';
        }

        try {
            $this->logger->info('PayPal webhook: Marking payment as failed', [
                'payment_id' => $paymentId,
                'reason' => $reason,
            ]);

            $failCommand = new MarkPaymentAsFailed(
                id: PaymentId::fromString($paymentId),
                errorMessage: "Payment capture denied: {$reason}",
                errorCode: $reason
            );

            $this->commandBus->dispatch($failCommand);

            $this->logger->info('PayPal webhook: Payment marked as failed', [
                'payment_id' => $paymentId,
            ]);

            return 'Payment failure recorded';
        } catch (\Exception $e) {
            $this->logger->error('PayPal webhook: Failed to mark payment as failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * PAYMENT.CAPTURE.REFUNDED - Refund processed.
     *
     * @param array<string, mixed> $eventData
     */
    private function handleCaptureRefunded(array $eventData): string
    {
        $resource = $eventData['resource'] ?? [];
        $refundId = $resource['id'] ?? null;
        $captureId = $resource['links'][0]['href'] ?? null; // Link to original capture

        $this->logger->info('PayPal webhook: Payment refunded', [
            'refund_id' => $refundId,
            'capture_id' => $captureId,
            'status' => $resource['status'] ?? null,
        ]);

        // Refund-ul este deja procesat prin command handler
        // Acest webhook este doar o confirmare

        return 'Refund confirmed';
    }

    /**
     * PAYMENT.AUTHORIZATION.CREATED - Authorization completed.
     *
     * @param array<string, mixed> $eventData
     */
    private function handleAuthorizationCreated(array $eventData): string
    {
        $resource = $eventData['resource'] ?? [];
        $authorizationId = $resource['id'] ?? null;

        $this->logger->info('PayPal webhook: Authorization created', [
            'authorization_id' => $authorizationId,
            'status' => $resource['status'] ?? null,
        ]);

        // Payment authorization is handled by the gateway adapter
        // This webhook is just for logging/monitoring
        return 'Authorization logged';
    }

    /**
     * PAYMENT.AUTHORIZATION.VOIDED - Authorization voided.
     *
     * @param array<string, mixed> $eventData
     */
    private function handleAuthorizationVoided(array $eventData): string
    {
        $resource = $eventData['resource'] ?? [];
        $authorizationId = $resource['id'] ?? null;

        $this->logger->info('PayPal webhook: Authorization voided', [
            'authorization_id' => $authorizationId,
            'status' => $resource['status'] ?? null,
        ]);

        // Extract payment_id
        $customId = $resource['custom_id'] ?? null;
        $paymentId = $resource['supplementary_data']['related_ids']['order_id'] ?? $customId;

        if (!$paymentId) {
            $this->logger->warning('PayPal webhook: Missing payment_id for voided authorization', [
                'authorization_id' => $authorizationId,
            ]);

            return 'Missing payment_id';
        }

        try {
            // Mark payment as failed (authorization was voided)
            $failCommand = new MarkPaymentAsFailed(
                id: PaymentId::fromString($paymentId),
                errorMessage: 'Payment authorization was voided',
                errorCode: 'authorization_voided'
            );

            $this->commandBus->dispatch($failCommand);

            $this->logger->info('PayPal webhook: Payment marked as failed due to voided authorization', [
                'payment_id' => $paymentId,
            ]);

            return 'Authorization void processed';
        } catch (\Exception $e) {
            $this->logger->error('PayPal webhook: Failed to process voided authorization', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * CHECKOUT.ORDER.APPROVED - Customer approved order.
     *
     * @param array<string, mixed> $eventData
     */
    private function handleOrderApproved(array $eventData): string
    {
        $resource = $eventData['resource'] ?? [];
        $orderId = $resource['id'] ?? null;

        $this->logger->info('PayPal webhook: Order approved by customer', [
            'order_id' => $orderId,
            'status' => $resource['status'] ?? null,
        ]);

        // Order approval is handled by the gateway adapter during confirmPaymentIntent
        // This webhook is just for logging/monitoring
        return 'Order approval logged';
    }

    /**
     * Unknown event type - just log it.
     *
     * @param array<string, mixed> $eventData
     */
    private function handleUnknownEvent(array $eventData): string
    {
        $this->logger->info('PayPal webhook: Unknown event type', [
            'event_id' => $eventData['id'] ?? 'unknown',
            'event_type' => $eventData['event_type'] ?? 'unknown',
        ]);

        return 'Event received';
    }
}
