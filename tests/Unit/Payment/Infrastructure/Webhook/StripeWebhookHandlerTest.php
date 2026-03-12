<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Webhook;

use App\Payment\Application\Service\WebhookDeduplicationService;
use App\Payment\Infrastructure\Webhook\StripeWebhookHandler;
use App\Shared\Application\Service\TenantContextInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Unit tests for StripeWebhookHandler.
 *
 * We generate real Stripe-compatible HMAC-SHA256 signatures so that
 * Webhook::constructEvent() passes verification, letting us exercise all
 * private handler paths without needing functional tests or mocked static methods.
 */
#[CoversClass(StripeWebhookHandler::class)]
#[Group('payment')]
#[Group('webhook')]
final class StripeWebhookHandlerTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_secret_key_for_unit_tests';
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const VALID_PAYMENT_UUID = '01912345-abcd-7000-8000-000000000001';

    private StripeWebhookHandler $handler;
    private MockObject&MessageBusInterface $commandBus;
    private MockObject&MessageBusInterface $queryBus;
    private MockObject&WebhookDeduplicationService $deduplicationService;
    private MockObject&TenantContextInterface $tenantContext;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->deduplicationService = $this->createMock(WebhookDeduplicationService::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new StripeWebhookHandler(
            webhookSecret: self::WEBHOOK_SECRET,
            commandBus: $this->commandBus,
            queryBus: $this->queryBus,
            deduplicationService: $this->deduplicationService,
            tenantContext: $this->tenantContext,
            logger: $this->logger,
        );
    }

    // -----------------------------------------------------------------------
    // Helper: build a valid Stripe signature header
    // -----------------------------------------------------------------------

    /**
     * Build a stripe-signature header that satisfies WebhookSignature::verifyHeader().
     *
     * Format: t={timestamp},v1={hmac}
     * HMAC input: "{timestamp}.{payload}"
     */
    private function buildStripeSignatureHeader(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Build a Request that carries a valid Stripe signature and a given JSON payload.
     */
    private function buildSignedRequest(string $payload): Request
    {
        $sigHeader = $this->buildStripeSignatureHeader($payload);
        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sigHeader,
        ], $payload);

        return $request;
    }

    /**
     * Build a standard Stripe PaymentIntent event payload as JSON string.
     *
     * @param array<string, mixed> $metadata
     */
    private function buildPaymentIntentPayload(
        string $eventType,
        string $eventId = 'evt_test_001',
        string $paymentIntentId = 'pi_test_001',
        array $metadata = [],
        ?string $lastPaymentErrorMessage = null,
        ?string $lastPaymentErrorCode = null,
        ?string $cancellationReason = null,
    ): string {
        $paymentIntentData = [
            'id' => $paymentIntentId,
            'amount' => 9999,
            'currency' => 'eur',
            'status' => 'succeeded',
            'metadata' => $metadata,
        ];

        if (null !== $cancellationReason) {
            $paymentIntentData['cancellation_reason'] = $cancellationReason;
        }

        if (null !== $lastPaymentErrorMessage) {
            $paymentIntentData['last_payment_error'] = [
                'message' => $lastPaymentErrorMessage,
                'code' => $lastPaymentErrorCode,
            ];
        }

        return (string) json_encode([
            'id' => $eventId,
            'type' => $eventType,
            'data' => [
                'object' => $paymentIntentData,
            ],
        ]);
    }

    /**
     * Build a Stripe Charge event payload as JSON string.
     */
    private function buildChargePayload(
        string $chargeId = 'ch_test_001',
        int $amountRefunded = 5000,
        bool $refunded = true,
    ): string {
        return (string) json_encode([
            'id' => 'evt_charge_001',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => $chargeId,
                    'amount_refunded' => $amountRefunded,
                    'refunded' => $refunded,
                    'metadata' => [],
                ],
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Missing / invalid signature header
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturns400WhenSignatureHeaderMissing(): void
    {
        $request = new Request();
        $request->initialize([], [], [], [], [], [], '{"event": "test"}');

        $this->logger->expects(self::once())->method('error')
            ->with('Stripe webhook: Missing signature header');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Missing signature', $response->getContent());
    }

    #[Test]
    public function itReturns400WhenSignatureIsInvalid(): void
    {
        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=9999999999,v1=invalidsignature',
        ], '{"id":"evt_test"}');

        $this->logger->expects(self::once())->method('error')
            ->with('Stripe webhook: Invalid signature', self::callback(fn ($ctx) => isset($ctx['error'])));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Invalid signature', $response->getContent());
    }

    #[Test]
    public function itReturns500WhenProcessingThrowsGenericException(): void
    {
        // Build payload that will pass signature verification but break on deduplication
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.succeeded',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        // deduplicationService throws — this is caught by the outer \Exception handler
        $this->deduplicationService->method('isDuplicate')
            ->willThrowException(new \RuntimeException('Redis connection lost'));

        $this->logger->expects(self::atLeastOnce())->method('error')
            ->with(
                'Stripe webhook: Processing failed',
                self::callback(fn ($ctx) => isset($ctx['error'])),
            );

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('Webhook processing failed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Deduplication: Already processed
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsOkWhenEventIsAlreadyProcessed(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.succeeded',
            eventId: 'evt_dup_001',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $tenantContextSet = false;
        $this->tenantContext->expects(self::once())
            ->method('setCurrentTenant')
            ->with(self::callback(static function ($tenantId) use (&$tenantContextSet): bool {
                $tenantContextSet = true;

                return self::DEFAULT_TENANT_ID === $tenantId->toString();
            }));

        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->willReturnCallback(static function () use (&$tenantContextSet): bool {
                self::assertTrue($tenantContextSet);

                return true;
            });

        $this->queryBus->expects(self::never())->method('dispatch');
        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Already processed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // payment_intent.succeeded: all sub-paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesPaymentIntentSucceededWhenMissingMetadata(): void
    {
        // No payment_id / tenant_id in metadata — now returns 400 (tenant_id required)
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.succeeded',
            metadata: [],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->expects(self::never())->method('isDuplicate');
        $this->queryBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Missing tenant_id in metadata', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentSucceededWhenPaymentNotFound(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.succeeded',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        // queryBus returns envelope with no HandledStamp result
        $envelope = new Envelope(new \stdClass());
        $this->queryBus->expects(self::once())->method('dispatch')->willReturn($envelope);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->logger->expects(self::atLeastOnce())->method('warning')
            ->with('Stripe webhook: Payment not found', self::anything());

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment not found', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentSucceededAndCapturesPayment(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.succeeded',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        // Build a DTO-like object with status !== 'captured'
        $paymentDTO = new \stdClass();
        $paymentDTO->status = 'authorized';

        $handledStamp = new HandledStamp($paymentDTO, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);
        $this->queryBus->expects(self::once())->method('dispatch')->willReturn($envelope);

        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment processed', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentSucceededWhenAlreadyCaptured(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.succeeded',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $paymentDTO = new \stdClass();
        $paymentDTO->status = 'captured';

        $handledStamp = new HandledStamp($paymentDTO, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);
        $this->queryBus->expects(self::once())->method('dispatch')->willReturn($envelope);

        // commandBus must NOT be dispatched since payment is already captured
        $this->commandBus->expects(self::never())->method('dispatch');

        // logger->info() is called multiple times; we just verify no error occurs
        // logger info is void - no return needed

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment processed', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentSucceededAndRethrowsOnCommandBusFailure(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.succeeded',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $paymentDTO = new \stdClass();
        $paymentDTO->status = 'authorized';

        $handledStamp = new HandledStamp($paymentDTO, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Command handler failed'));

        $response = $this->handler->handle($request);

        // Exception is rethrown from private handler, caught by outer \Exception handler
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('Webhook processing failed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // payment_intent.payment_failed: all sub-paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesPaymentIntentFailedWhenMissingMetadata(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.payment_failed',
            metadata: [],
            lastPaymentErrorMessage: 'Card declined',
            lastPaymentErrorCode: 'card_declined',
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->expects(self::never())->method('isDuplicate');
        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Missing tenant_id in metadata', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentFailedAndMarksPaymentFailed(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.payment_failed',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
            lastPaymentErrorMessage: 'Insufficient funds',
            lastPaymentErrorCode: 'insufficient_funds',
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);
        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment failure processed', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentFailedWithNoLastPaymentError(): void
    {
        // last_payment_error is absent — handler uses 'Unknown payment error' default
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.payment_failed',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);
        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment failure processed', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentFailedAndRethrowsOnCommandBusFailure(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.payment_failed',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
            lastPaymentErrorMessage: 'Timeout',
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);
        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Bus failure'));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // payment_intent.canceled
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesPaymentIntentCanceledWithPaymentId(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.canceled',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
            cancellationReason: 'abandoned',
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);
        $this->commandBus->expects(self::never())->method('dispatch');

        // logger->info() is called multiple times; stub it to allow any calls
        // logger info is void - no return needed

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Cancellation confirmed', $response->getContent());
    }

    #[Test]
    public function itHandlesPaymentIntentCanceledWithoutPaymentId(): void
    {
        // No payment_id in metadata but valid tenant_id — handler logs and returns Cancellation confirmed
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.canceled',
            metadata: [
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
            cancellationReason: 'fraudulent',
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->willReturn(false);
        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Cancellation confirmed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // charge.refunded
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesChargeRefunded(): void
    {
        // charge.refunded events must include tenant_id in metadata for the handler to proceed
        $payload = (string) json_encode([
            'id' => 'evt_charge_001',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_test_refund',
                    'amount_refunded' => 4999,
                    'refunded' => true,
                    'metadata' => [
                        'tenant_id' => self::DEFAULT_TENANT_ID,
                    ],
                ],
            ],
        ]);
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->willReturn(false);
        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Refund confirmed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Unknown event type
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesUnknownEventType(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'customer.created',
            metadata: [
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->method('isDuplicate')->willReturn(false);
        $this->commandBus->expects(self::never())->method('dispatch');

        // logger->info() is called multiple times; stub it to allow any calls
        // logger info is void - no return needed

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Event received', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Deduplication: not a duplicate (records event)
    // -----------------------------------------------------------------------

    #[Test]
    public function itCallsDeduplicationServiceWhenTenantIdPresent(): void
    {
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.canceled',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => self::DEFAULT_TENANT_ID,
            ],
        );
        $request = $this->buildSignedRequest($payload);

        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->with(
                'stripe',
                self::anything(), // event id
                'payment_intent.canceled',
                self::anything(), // TenantId instance
                $payload,
            )
            ->willReturn(false);

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function itSkipsDeduplicationWhenTenantIdAbsentOrInvalid(): void
    {
        // Metadata has an invalid (non-UUID-v4) tenant_id value
        $payload = $this->buildPaymentIntentPayload(
            eventType: 'payment_intent.canceled',
            metadata: [
                'payment_id' => self::VALID_PAYMENT_UUID,
                'tenant_id' => 'not-a-uuid',
            ],
        );
        $request = $this->buildSignedRequest($payload);

        // extractTenantIdFromEvent() throws InvalidArgumentException → returns null
        // handler now returns 400 "Missing tenant_id in metadata"
        $this->tenantContext->expects(self::never())->method('setCurrentTenant');
        $this->deduplicationService->expects(self::never())->method('isDuplicate');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Missing tenant_id in metadata', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Handler can be instantiated (constructor coverage)
    // -----------------------------------------------------------------------

    #[Test]
    public function itCanBeInstantiatedWithValidDependencies(): void
    {
        $handler = new StripeWebhookHandler(
            webhookSecret: 'whsec_any_secret',
            commandBus: $this->commandBus,
            queryBus: $this->queryBus,
            deduplicationService: $this->deduplicationService,
            tenantContext: $this->tenantContext,
            logger: $this->logger,
        );

        self::assertInstanceOf(StripeWebhookHandler::class, $handler);
    }
}
