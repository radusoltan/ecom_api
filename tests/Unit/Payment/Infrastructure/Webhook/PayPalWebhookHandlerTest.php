<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Webhook;

use App\Payment\Application\Service\WebhookDeduplicationService;
use App\Payment\Infrastructure\Webhook\PayPalWebhookHandler;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Unit tests for PayPalWebhookHandler.
 *
 * All HTTP calls to PayPal (OAuth token + signature verification) are mocked
 * via the injected HttpClientInterface, allowing every code path to be exercised
 * without any real network access.
 */
#[CoversClass(PayPalWebhookHandler::class)]
#[Group('payment')]
#[Group('webhook')]
final class PayPalWebhookHandlerTest extends TestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const VALID_PAYMENT_UUID = '01912345-abcd-7000-8000-000000000001';

    private PayPalWebhookHandler $handler;
    private MockObject&HttpClientInterface $httpClient;
    private MockObject&MessageBusInterface $commandBus;
    private MockObject&MessageBusInterface $queryBus;
    private MockObject&WebhookDeduplicationService $deduplicationService;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->deduplicationService = $this->createMock(WebhookDeduplicationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = $this->buildHandler(sandbox: true);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildHandler(bool $sandbox): PayPalWebhookHandler
    {
        return new PayPalWebhookHandler(
            webhookId: 'WEBHOOK-ID-TEST-001',
            clientId: 'test_client_id',
            clientSecret: 'test_client_secret',
            httpClient: $this->httpClient,
            commandBus: $this->commandBus,
            queryBus: $this->queryBus,
            deduplicationService: $this->deduplicationService,
            logger: $this->logger,
            sandbox: $sandbox,
        );
    }

    /**
     * Build a valid PayPal webhook request with all required headers.
     *
     * @param array<string, mixed> $payload
     */
    private function buildRequest(array $payload): Request
    {
        $json = (string) json_encode($payload);
        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'test_sig_value',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'transmission-id-001',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2025-01-01T00:00:00Z',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert/test',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        ], $json);

        return $request;
    }

    /** Configure httpClient to return a successful OAuth token. */
    private function mockSuccessfulOAuth(): MockObject&ResponseInterface
    {
        $oauthResponse = $this->createMock(ResponseInterface::class);
        $oauthResponse->method('toArray')->willReturn(['access_token' => 'test_access_token_abc']);

        return $oauthResponse;
    }

    /** Configure httpClient to return signature SUCCESS. */
    private function mockSuccessfulVerification(): MockObject&ResponseInterface
    {
        $verifyResponse = $this->createMock(ResponseInterface::class);
        $verifyResponse->method('toArray')->willReturn(['verification_status' => 'SUCCESS']);

        return $verifyResponse;
    }

    /** Configure httpClient to return signature FAILURE. */
    private function mockFailedVerification(): MockObject&ResponseInterface
    {
        $verifyResponse = $this->createMock(ResponseInterface::class);
        $verifyResponse->method('toArray')->willReturn(['verification_status' => 'FAILURE']);

        return $verifyResponse;
    }

    /**
     * Configure httpClient for success flow: 1st call = OAuth, 2nd call = verify SUCCESS.
     */
    private function mockHttpForSuccessfulSignatureVerification(): void
    {
        $this->httpClient->expects(self::exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->mockSuccessfulOAuth(),
                $this->mockSuccessfulVerification(),
            );
    }

    /**
     * Build an Envelope with a HandledStamp containing the given DTO.
     */
    private function buildQueryEnvelope(object $dto): Envelope
    {
        $handledStamp = new HandledStamp($dto, 'handler');

        return new Envelope(new \stdClass(), [$handledStamp]);
    }

    /**
     * Build an Envelope with no HandledStamp (simulates "payment not found").
     */
    private function buildEmptyEnvelope(): Envelope
    {
        return new Envelope(new \stdClass());
    }

    // -----------------------------------------------------------------------
    // Missing signature header
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturns400WhenTransmissionSigHeaderMissing(): void
    {
        $request = new Request();
        $request->initialize([], [], [], [], [], [], '{"event": "test"}');

        $this->logger->expects(self::once())->method('error')
            ->with('PayPal webhook: Missing transmission signature header');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Missing signature', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Invalid JSON payload
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturns400WhenPayloadIsInvalidJson(): void
    {
        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'test_sig',
        ], '{invalid json{{');

        $this->logger->expects(self::once())->method('error')
            ->with(
                'PayPal webhook: Invalid JSON payload',
                self::callback(fn ($ctx) => isset($ctx['error'])),
            );

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Invalid JSON', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Signature verification
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturns400WhenSignatureVerificationReturnsFailure(): void
    {
        $payload = ['id' => 'evt_001', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];
        $request = $this->buildRequest($payload);

        $this->httpClient->expects(self::exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->mockSuccessfulOAuth(),
                $this->mockFailedVerification(),
            );

        $this->logger->expects(self::atLeastOnce())->method('error')
            ->with('PayPal webhook: Invalid signature', self::anything());

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Invalid signature', $response->getContent());
    }

    #[Test]
    public function itReturns400WhenOauthTokenRequestThrows(): void
    {
        $payload = ['id' => 'evt_002', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];
        $request = $this->buildRequest($payload);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection refused'));

        // verifySignature() logs error, then handle() logs invalid signature
        $this->logger->expects(self::exactly(2))->method('error');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Invalid signature', $response->getContent());
    }

    #[Test]
    public function itUsesProductionUrlWhenSandboxFalse(): void
    {
        $this->handler = $this->buildHandler(sandbox: false);

        $payload = ['id' => 'evt_prod', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];
        $request = $this->buildRequest($payload);

        $oauthResponse = $this->createMock(ResponseInterface::class);
        $oauthResponse->method('toArray')->willReturn(['access_token' => 'prod_token']);

        $verifyResponse = $this->createMock(ResponseInterface::class);
        $verifyResponse->method('toArray')->willReturn(['verification_status' => 'FAILURE']);

        // Check that the production OAuth URL is used
        $this->httpClient->expects(self::exactly(2))
            ->method('request')
            ->with(
                self::anything(),
                self::callback(fn ($url) => str_contains($url, 'api-m.paypal.com') && !str_contains($url, 'sandbox')),
                self::anything(),
            )
            ->willReturnOnConsecutiveCalls($oauthResponse, $verifyResponse);

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Deduplication
    // -----------------------------------------------------------------------

    #[Test]
    public function itSkipsProcessingWhenEventIsDuplicate(): void
    {
        $payload = [
            'id' => 'evt_dup_001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'custom_id_tenant' => self::DEFAULT_TENANT_ID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->willReturn(true);

        $this->queryBus->expects(self::never())->method('dispatch');
        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Already processed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // PAYMENT.CAPTURE.COMPLETED
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesCaptureCompletedWithMissingPaymentId(): void
    {
        $payload = [
            'id' => 'evt_cap_001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_001',
                // no custom_id or supplementary_data
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->queryBus->expects(self::never())->method('dispatch');

        $this->logger->expects(self::atLeastOnce())->method('warning')
            ->with('PayPal webhook: Missing payment_id', self::anything());

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Missing metadata', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureCompletedWhenPaymentNotFound(): void
    {
        $payload = [
            'id' => 'evt_cap_002',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_002',
                'custom_id' => self::VALID_PAYMENT_UUID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->queryBus->expects(self::once())->method('dispatch')
            ->willReturn($this->buildEmptyEnvelope());

        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment not found', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureCompletedAndCapturesPayment(): void
    {
        $payload = [
            'id' => 'evt_cap_003',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_003',
                'custom_id' => self::VALID_PAYMENT_UUID,
                'status' => 'COMPLETED',
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $paymentDTO = new \stdClass();
        $paymentDTO->status = 'authorized';

        $this->queryBus->expects(self::once())->method('dispatch')
            ->willReturn($this->buildQueryEnvelope($paymentDTO));

        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment processed', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureCompletedWhenAlreadyCaptured(): void
    {
        $payload = [
            'id' => 'evt_cap_004',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_004',
                'custom_id' => self::VALID_PAYMENT_UUID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $paymentDTO = new \stdClass();
        $paymentDTO->status = 'captured';

        $this->queryBus->expects(self::once())->method('dispatch')
            ->willReturn($this->buildQueryEnvelope($paymentDTO));

        $this->commandBus->expects(self::never())->method('dispatch');

        // logger->info() is called multiple times with different messages; stub it
        // logger info is void - no return needed

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment processed', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureCompletedWithSupplementaryDataPaymentId(): void
    {
        $payload = [
            'id' => 'evt_cap_005',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_005',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => self::VALID_PAYMENT_UUID,
                    ],
                ],
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $paymentDTO = new \stdClass();
        $paymentDTO->status = 'authorized';

        $this->queryBus->expects(self::once())->method('dispatch')
            ->willReturn($this->buildQueryEnvelope($paymentDTO));

        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment processed', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureCompletedRethrowsOnCommandBusFailure(): void
    {
        $payload = [
            'id' => 'evt_cap_006',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_006',
                'custom_id' => self::VALID_PAYMENT_UUID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $paymentDTO = new \stdClass();
        $paymentDTO->status = 'authorized';

        $this->queryBus->method('dispatch')->willReturn($this->buildQueryEnvelope($paymentDTO));
        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Command bus failure'));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('Webhook processing failed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // PAYMENT.CAPTURE.DENIED
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesCaptureDeniedWithMissingPaymentId(): void
    {
        $payload = [
            'id' => 'evt_denied_001',
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => [
                'id' => 'capture_denied_001',
                'status' => 'DENIED',
                'status_details' => ['reason' => 'BUYER_CANNOT_PAY'],
                // no custom_id
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Missing payment_id', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureDeniedAndMarksPaymentFailed(): void
    {
        $payload = [
            'id' => 'evt_denied_002',
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => [
                'id' => 'capture_denied_002',
                'status' => 'DENIED',
                'custom_id' => self::VALID_PAYMENT_UUID,
                'status_details' => ['reason' => 'INSTRUMENT_DECLINED'],
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment failure recorded', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureDeniedWithNoStatusDetails(): void
    {
        $payload = [
            'id' => 'evt_denied_003',
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => [
                'id' => 'capture_denied_003',
                'status' => 'DENIED',
                'custom_id' => self::VALID_PAYMENT_UUID,
                // no status_details
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Payment failure recorded', $response->getContent());
    }

    #[Test]
    public function itHandlesCaptureDeniedRethrowsOnCommandBusFailure(): void
    {
        $payload = [
            'id' => 'evt_denied_004',
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => [
                'id' => 'capture_denied_004',
                'custom_id' => self::VALID_PAYMENT_UUID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Bus failure'));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // PAYMENT.CAPTURE.REFUNDED
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesCaptureRefunded(): void
    {
        $payload = [
            'id' => 'evt_refund_001',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_001',
                'status' => 'COMPLETED',
                'links' => [['href' => 'https://api.paypal.com/v2/payments/captures/capture_001']],
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Refund confirmed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // PAYMENT.AUTHORIZATION.CREATED
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesAuthorizationCreated(): void
    {
        $payload = [
            'id' => 'evt_auth_001',
            'event_type' => 'PAYMENT.AUTHORIZATION.CREATED',
            'resource' => [
                'id' => 'auth_001',
                'status' => 'CREATED',
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::never())->method('dispatch');

        // logger->info() is called multiple times; stub it to allow any calls
        // logger info is void - no return needed

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Authorization logged', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // PAYMENT.AUTHORIZATION.VOIDED
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesAuthorizationVoidedWithMissingPaymentId(): void
    {
        $payload = [
            'id' => 'evt_void_001',
            'event_type' => 'PAYMENT.AUTHORIZATION.VOIDED',
            'resource' => [
                'id' => 'auth_voided_001',
                'status' => 'VOIDED',
                // no custom_id
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::never())->method('dispatch');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Missing payment_id', $response->getContent());
    }

    #[Test]
    public function itHandlesAuthorizationVoidedAndMarksPaymentFailed(): void
    {
        $payload = [
            'id' => 'evt_void_002',
            'event_type' => 'PAYMENT.AUTHORIZATION.VOIDED',
            'resource' => [
                'id' => 'auth_voided_002',
                'status' => 'VOIDED',
                'custom_id' => self::VALID_PAYMENT_UUID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Authorization void processed', $response->getContent());
    }

    #[Test]
    public function itHandlesAuthorizationVoidedWithSupplementaryDataPaymentId(): void
    {
        $payload = [
            'id' => 'evt_void_003',
            'event_type' => 'PAYMENT.AUTHORIZATION.VOIDED',
            'resource' => [
                'id' => 'auth_voided_003',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => self::VALID_PAYMENT_UUID,
                    ],
                ],
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Authorization void processed', $response->getContent());
    }

    #[Test]
    public function itHandlesAuthorizationVoidedRethrowsOnCommandBusFailure(): void
    {
        $payload = [
            'id' => 'evt_void_004',
            'event_type' => 'PAYMENT.AUTHORIZATION.VOIDED',
            'resource' => [
                'id' => 'auth_voided_004',
                'custom_id' => self::VALID_PAYMENT_UUID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Bus failure'));

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // CHECKOUT.ORDER.APPROVED
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesOrderApproved(): void
    {
        $payload = [
            'id' => 'evt_order_001',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'order_001',
                'status' => 'APPROVED',
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::never())->method('dispatch');

        // logger->info() is called multiple times; stub it to allow any calls
        // logger info is void - no return needed

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Order approval logged', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Unknown event type
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesUnknownEventType(): void
    {
        $payload = [
            'id' => 'evt_unknown_001',
            'event_type' => 'SOME.UNKNOWN.EVENT',
            'resource' => [],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $this->commandBus->expects(self::never())->method('dispatch');

        // logger->info() is called multiple times; stub it to allow any calls
        // logger info is void - no return needed

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Event received', $response->getContent());
    }

    #[Test]
    public function itHandlesNullEventType(): void
    {
        $payload = [
            'id' => 'evt_null_type_001',
            // no event_type key
            'resource' => [],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();
        $this->deduplicationService->method('isDuplicate')->willReturn(false);

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Event received', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // extractTenantIdFromEventData: all paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itExtractsTenantIdFromCustomIdTenant(): void
    {
        $payload = [
            'id' => 'evt_tenant_001',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_tenant_001',
                'custom_id_tenant' => self::DEFAULT_TENANT_ID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        // Deduplication IS called because tenant_id was extracted
        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->with('paypal', 'evt_tenant_001', 'PAYMENT.CAPTURE.REFUNDED', self::anything(), self::anything())
            ->willReturn(false);

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function itExtractsTenantIdFromSupplementaryData(): void
    {
        $payload = [
            'id' => 'evt_tenant_002',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_tenant_002',
                'supplementary_data' => [
                    'related_ids' => [
                        'tenant_id' => self::DEFAULT_TENANT_ID,
                    ],
                ],
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->willReturn(false);

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function itExtractsTenantIdFromPurchaseUnits(): void
    {
        $payload = [
            'id' => 'evt_tenant_003',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_tenant_003',
                'purchase_units' => [
                    ['custom_id_tenant' => self::DEFAULT_TENANT_ID],
                ],
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        $this->deduplicationService->expects(self::once())
            ->method('isDuplicate')
            ->willReturn(false);

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function itSkipsDeduplicationWhenNoTenantIdFound(): void
    {
        $payload = [
            'id' => 'evt_no_tenant_001',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_no_tenant',
                // no tenant_id anywhere
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        $this->deduplicationService->expects(self::never())->method('isDuplicate');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function itSkipsDeduplicationWhenTenantIdIsInvalidUuid(): void
    {
        $payload = [
            'id' => 'evt_bad_tenant_001',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_bad_tenant',
                'custom_id_tenant' => 'not-a-valid-uuid-v4',
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        // extractTenantIdFromEventData() catches InvalidArgumentException → returns null
        $this->deduplicationService->expects(self::never())->method('isDuplicate');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function itSkipsDeduplicationWhenTenantIdIsEmptyString(): void
    {
        $payload = [
            'id' => 'evt_empty_tenant_001',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_empty_tenant',
                'custom_id_tenant' => '',
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        $this->deduplicationService->expects(self::never())->method('isDuplicate');

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Outer exception handler (Processing failed)
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturns500WhenDeduplicationServiceThrows(): void
    {
        $payload = [
            'id' => 'evt_crash_001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_crash',
                'custom_id_tenant' => self::DEFAULT_TENANT_ID,
            ],
        ];
        $request = $this->buildRequest($payload);

        $this->mockHttpForSuccessfulSignatureVerification();

        $this->deduplicationService->method('isDuplicate')
            ->willThrowException(new \RuntimeException('Redis down'));

        $this->logger->expects(self::atLeastOnce())->method('error')
            ->with('PayPal webhook: Processing failed', self::anything());

        $response = $this->handler->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('Webhook processing failed', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Constructor coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itCanBeInstantiatedInSandboxMode(): void
    {
        $handler = $this->buildHandler(sandbox: true);

        self::assertInstanceOf(PayPalWebhookHandler::class, $handler);
    }

    #[Test]
    public function itCanBeInstantiatedInProductionMode(): void
    {
        $handler = $this->buildHandler(sandbox: false);

        self::assertInstanceOf(PayPalWebhookHandler::class, $handler);
    }
}
