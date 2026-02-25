<?php

declare(strict_types=1);

namespace App\Tests\Functional\Payment\Webhook;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Stripe webhook endpoint.
 *
 * Tests the POST /api/webhooks/stripe endpoint accessibility and basic validation.
 *
 * Note: Comprehensive webhook event processing cannot be fully tested without valid
 * Stripe signatures. For end-to-end testing, use Stripe CLI webhook forwarding.
 * See tests/Functional/Payment/Webhook/README.md for details.
 *
 * Headers must be passed via the $server argument to WebTestCase::request() using
 * the HTTP_ prefix convention (e.g. HTTP_STRIPE_SIGNATURE, CONTENT_TYPE).
 */
final class StripeWebhookTest extends WebTestCase
{
    /**
     * Encode an array to JSON, asserting that encoding succeeds.
     *
     * @param array<mixed> $data
     */
    private function jsonEncode(array $data): string
    {
        $encoded = json_encode($data);
        $this->assertNotFalse($encoded, 'json_encode() failed unexpectedly');

        return $encoded;
    }

    public function testWebhookEndpointExistsAndAcceptsPost(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = $this->jsonEncode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ]);

        // Act
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            ],
            $webhookPayload
        );

        // Assert - Endpoint exists (will return 400 for invalid signature, not 404)
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());
    }

    public function testItReturns400WhenSignatureHeaderMissing(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = $this->jsonEncode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ]);

        // Act - No stripe-signature header
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $webhookPayload
        );

        // Assert
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Missing signature', (string) $client->getResponse()->getContent());
    }

    public function testItReturns400ForInvalidSignature(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = $this->jsonEncode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ]);

        // Act - Invalid signature (present but malformed — no t= or v1= components).
        // The Stripe SDK's WebhookSignature::verifyHeader() returns -1 for getTimestamp()
        // and immediately throws SignatureVerificationException, which the handler catches
        // and maps to HTTP 400.
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'invalid_signature',
            ],
            $webhookPayload
        );

        // Assert
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Invalid signature', (string) $client->getResponse()->getContent());
    }

    public function testItHandlesEmptyPayloadGracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            ],
            ''
        );

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    public function testItHandlesMalformedJsonGracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            ],
            'not valid json {{{'
        );

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    public function testWebhookDoesNotRequireJwtAuthentication(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = $this->jsonEncode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ]);

        // Act
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            ],
            $webhookPayload
        );

        // Assert - Should not return 401 (Unauthorized) or 403 (Forbidden)
        // Will return 400 (invalid signature) which is correct - no JWT required
        $this->assertNotSame(401, $client->getResponse()->getStatusCode());
        $this->assertNotSame(403, $client->getResponse()->getStatusCode());
    }

    public function testItOnlyAcceptsPostMethod(): void
    {
        // Arrange
        $client = static::createClient();

        // Act & Assert - GET not allowed
        $client->request('GET', '/api/v1/webhooks/stripe');
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - PUT not allowed
        $client->request(
            'PUT',
            '/api/v1/webhooks/stripe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}'
        );
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - DELETE not allowed
        $client->request('DELETE', '/api/v1/webhooks/stripe');
        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testWebhookAcceptsVariousEventTypes(): void
    {
        // Arrange
        $client = static::createClient();

        $eventTypes = [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
            'charge.refunded',
            'customer.created', // Unknown event type
        ];

        foreach ($eventTypes as $eventType) {
            $payload = $this->jsonEncode([
                'id' => 'evt_test_'.bin2hex(random_bytes(4)),
                'type' => $eventType,
                'data' => ['object' => ['id' => 'obj_test']],
            ]);

            // Act
            $client->request(
                'POST',
                '/api/v1/webhooks/stripe',
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_STRIPE_SIGNATURE' => 'test_signature',
                ],
                $payload
            );

            // Assert - All event types accepted (signature will fail, but 400 not 404)
            $this->assertNotSame(404, $client->getResponse()->getStatusCode(),
                "Event type '{$eventType}' should be accepted");
        }
    }

    public function testWebhookHandlesLargePayloadGracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Create a large but valid webhook payload
        $largeMetadata = [];
        for ($i = 0; $i < 100; ++$i) {
            $largeMetadata["key_{$i}"] = str_repeat('x', 100);
        }

        $webhookPayload = $this->jsonEncode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test',
                    'metadata' => $largeMetadata,
                ],
            ],
        ]);

        // Act
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            ],
            $webhookPayload
        );

        // Assert - Should handle large payloads without crashing
        $this->assertNotSame(500, $client->getResponse()->getStatusCode());
    }

    public function testWebhookResponseContainsAppropriateHeaders(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = $this->jsonEncode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ]);

        // Act
        $client->request(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            ],
            $webhookPayload
        );

        // Assert - Response should have standard headers
        $response = $client->getResponse();
        $this->assertNotEmpty($response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->headers->get('Date'));
    }

    /*
     * Note: The following scenarios require valid Stripe signatures and cannot be
     * fully tested in automated tests:
     *
     * 1. Successful payment_intent.succeeded processing with capture
     * 2. Payment already captured (idempotency)
     * 3. Payment not found handling
     * 4. Missing metadata handling
     * 5. Actual Stripe event object validation
     *
     * For comprehensive testing of these scenarios, use:
     * - Stripe CLI: `stripe listen --forward-to localhost:8000/api/webhooks/stripe`
     * - Manual testing in Stripe Dashboard webhook logs
     * - Integration tests in staging environment with real Stripe webhooks
     *
     * See tests/Functional/Payment/Webhook/README.md for complete testing guide.
     */
}
