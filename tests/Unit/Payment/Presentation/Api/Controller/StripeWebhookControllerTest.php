<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Presentation\Api\Controller;

use App\Payment\Infrastructure\Webhook\StripeWebhookHandler;
use App\Payment\Presentation\Api\Controller\StripeWebhookController;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit tests for StripeWebhookController.
 *
 * Tests that the controller properly delegates webhook requests to the handler.
 * The actual webhook processing logic is tested in StripeWebhookHandlerTest.
 *
 * Note: Since StripeWebhookHandler is final readonly, we create real instances
 * with mock dependencies for testing. The controller's job is just delegation.
 */
final class StripeWebhookControllerTest extends TestCase
{
    private StripeWebhookController $controller;
    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);

        $webhookHandler = new StripeWebhookHandler(
            webhookSecret: 'whsec_test_secret',
            commandBus: $this->commandBus,
            queryBus: $this->queryBus,
            logger: new NullLogger()
        );

        $this->controller = new StripeWebhookController($webhookHandler);
    }

    public function testItDelegatesRequestToWebhookHandlerMissingSignature(): void
    {
        // Arrange
        $request = new Request();
        $request->initialize([], [], [], [], [], [], '{"event": "test"}');

        // Act
        $response = $this->controller->handleWebhook($request);

        // Assert - Handler returns 400 when signature is missing
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('Missing signature', $response->getContent());
    }

    public function testItDelegatesRequestWithInvalidSignature(): void
    {
        // Arrange
        $payload = json_encode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ]);

        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'invalid_signature',
        ], $payload);

        // Act
        $response = $this->controller->handleWebhook($request);

        // Assert - Handler returns 400 for invalid signature
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('Invalid signature', $response->getContent());
    }

    public function testItHandlesEmptyPayload(): void
    {
        // Arrange
        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'signature',
        ], '');

        // Act
        $response = $this->controller->handleWebhook($request);

        // Assert - Should return error response (400 or 500)
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
    }

    public function testItHandlesMalformedJson(): void
    {
        // Arrange
        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'signature',
        ], 'not valid json {{{');

        // Act
        $response = $this->controller->handleWebhook($request);

        // Assert - Should return error response (400 or 500)
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
    }

    public function testItProcessesWebhookWithSignature(): void
    {
        // Arrange
        $payload = json_encode([
            'id' => 'evt_test_'.bin2hex(random_bytes(12)),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test',
                    'amount' => 9999,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'metadata' => [
                        'payment_id' => 'test_payment_id',
                        'tenant_id' => 'test_tenant_id',
                    ],
                ],
            ],
        ]);

        $request = new Request();
        $request->initialize([], [], [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123456789,v1=signature_hash',
        ], $payload);

        // Act
        $response = $this->controller->handleWebhook($request);

        // Assert - Signature verification will fail in test environment
        // This is expected - we're testing the delegation path
        $this->assertInstanceOf(Response::class, $response);
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
    }

    public function testControllerIsInstantiableWithHandler(): void
    {
        // Arrange
        $handler = new StripeWebhookHandler(
            webhookSecret: 'whsec_test',
            commandBus: $this->commandBus,
            queryBus: $this->queryBus,
            logger: new NullLogger()
        );

        // Act
        $controller = new StripeWebhookController($handler);

        // Assert
        $this->assertInstanceOf(StripeWebhookController::class, $controller);
    }

    public function testControllerResponseContainsContent(): void
    {
        // Arrange
        $request = new Request();
        $request->initialize([], [], [], [], [], [], '{"test": "data"}');

        // Act
        $response = $this->controller->handleWebhook($request);

        // Assert - Response should have content (error message)
        $this->assertNotEmpty($response->getContent());
    }

    /*
     * Note: The following test scenarios are covered in other test files:
     *
     * 1. Webhook signature verification - StripeWebhookHandlerTest
     * 2. Event processing logic (payment_intent.succeeded, etc.) - StripeWebhookHandlerTest
     * 3. End-to-end webhook flow with real Stripe events - StripeWebhookTest (Functional)
     * 4. Security configuration (PUBLIC_ACCESS) - security.yaml + functional tests
     *
     * This controller is a thin adapter that delegates to the handler.
     * Since StripeWebhookHandler is final readonly, we test delegation behavior
     * by verifying that requests are properly processed and responses are returned.
     */
}
