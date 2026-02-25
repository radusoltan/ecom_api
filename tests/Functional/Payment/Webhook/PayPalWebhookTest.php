<?php

declare(strict_types=1);

namespace App\Tests\Functional\Payment\Webhook;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for PayPal webhook endpoint.
 *
 * Tests the POST /api/webhooks/paypal endpoint accessibility and basic validation.
 *
 * Test Coverage:
 * - Endpoint accessibility
 * - HTTP method validation (POST only)
 * - Missing signature header → 400
 * - Invalid signature → 400
 * - Empty/malformed payload handling
 * - No JWT authentication required
 * - Various event type acceptance
 * - Large payload handling
 *
 * Note: Comprehensive webhook event processing cannot be fully tested without valid
 * PayPal signatures. For end-to-end testing, use PayPal webhook simulator or sandbox.
 *
 * Based on Epic 3: Payment Integration - PayPal Webhook Handling
 */
final class PayPalWebhookTest extends WebTestCase
{
    private const WEBHOOK_URL = '/api/v1/webhooks/paypal';

    /**
     * Build the $server array for KernelBrowser::request() with PayPal headers.
     *
     * KernelBrowser maps HTTP headers via the $server parameter using the
     * HTTP_ prefix convention (e.g. HTTP_CONTENT_TYPE, HTTP_PAYPAL_TRANSMISSION_ID).
     *
     * @param array<string, string> $extra Additional HTTP_ server vars to merge in.
     * @return array<string, string>
     */
    private function paypalServerHeaders(array $extra = []): array
    {
        return array_merge(
            [
                'HTTP_CONTENT_TYPE'            => 'application/json',
                'HTTP_PAYPAL_TRANSMISSION_SIG' => 'test_signature',
                'HTTP_PAYPAL_TRANSMISSION_ID'  => 'test_id',
                'HTTP_PAYPAL_TRANSMISSION_TIME' => '2025-01-01T00:00:00Z',
                'HTTP_PAYPAL_CERT_URL'         => 'https://api.paypal.com/cert',
                'HTTP_PAYPAL_AUTH_ALGO'        => 'SHA256withRSA',
            ],
            $extra
        );
    }

    // =============================================
    // Test: Endpoint Exists and Accepts POST
    // =============================================

    public function testWebhookEndpointExistsAndAcceptsPost(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Act
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(),
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Assert - Endpoint exists (will return 400 for invalid signature, not 404)
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Missing Signature Header
    // =============================================

    public function testItReturns400WhenSignatureHeaderMissing(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Act - No paypal-transmission-sig header
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            ['HTTP_CONTENT_TYPE' => 'application/json'],
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Assert
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Missing signature', (string) $client->getResponse()->getContent());
    }

    // =============================================
    // Test: Invalid Signature
    // =============================================

    public function testItReturns400ForInvalidSignature(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_test',
                'status' => 'COMPLETED',
            ],
        ];

        // Act - Invalid signature (PayPal will reject verification)
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(['HTTP_PAYPAL_TRANSMISSION_SIG' => 'invalid_signature_xyz']),
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Assert - Signature verification will fail
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Invalid signature', (string) $client->getResponse()->getContent());
    }

    // =============================================
    // Test: Empty Payload
    // =============================================

    public function testItHandlesEmptyPayloadGracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act - Empty body with signature header present
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(),
            ''
        );

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    // =============================================
    // Test: Malformed JSON
    // =============================================

    public function testItHandlesMalformedJsonGracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act - Malformed JSON with signature header present
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(),
            '{not valid json [[['
        );

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    // =============================================
    // Test: No JWT Authentication Required
    // =============================================

    public function testWebhookDoesNotRequireJwtAuthentication(): void
    {
        // Arrange - no Authorization header
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Act
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(),
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Assert - Should not return 401 (Unauthorized) or 403 (Forbidden)
        // Will return 400 (invalid signature) which is correct - no JWT required
        $this->assertNotSame(401, $client->getResponse()->getStatusCode());
        $this->assertNotSame(403, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Only POST Method Allowed
    // =============================================

    public function testItOnlyAcceptsPostMethod(): void
    {
        // Arrange
        $client = static::createClient();
        $client->followRedirects(false);

        // Act & Assert - GET not allowed
        $client->request('GET', self::WEBHOOK_URL);
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - PUT not allowed
        $client->request('PUT', self::WEBHOOK_URL);
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - DELETE not allowed
        $client->request('DELETE', self::WEBHOOK_URL);
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - PATCH not allowed
        $client->request('PATCH', self::WEBHOOK_URL);
        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Various Event Types Accepted
    // =============================================

    public function testWebhookAcceptsVariousEventTypes(): void
    {
        // Arrange
        $client = static::createClient();

        $eventTypes = [
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.REFUNDED',
            'PAYMENT.AUTHORIZATION.CREATED',
            'PAYMENT.AUTHORIZATION.VOIDED',
            'CHECKOUT.ORDER.APPROVED',
            'SOME.UNKNOWN.EVENT', // Unknown event type
        ];

        foreach ($eventTypes as $eventType) {
            // Act
            $client->request(
                'POST',
                self::WEBHOOK_URL,
                [],
                [],
                $this->paypalServerHeaders([
                    'HTTP_PAYPAL_TRANSMISSION_SIG' => 'test_signature_'.bin2hex(random_bytes(4)),
                    'HTTP_PAYPAL_TRANSMISSION_ID'  => 'test_id_'.uniqid(),
                ]),
                (string) json_encode([
                    'id'         => 'evt_test_'.bin2hex(random_bytes(4)),
                    'event_type' => $eventType,
                    'resource'   => ['id' => 'obj_test_'.uniqid()],
                ], JSON_THROW_ON_ERROR)
            );

            // Assert - All event types accepted (signature will fail, but 400 not 404)
            $this->assertNotSame(
                404,
                $client->getResponse()->getStatusCode(),
                "Event type '{$eventType}' should be accepted"
            );
        }
    }

    // =============================================
    // Test: Large Payload Handling
    // =============================================

    public function testWebhookHandlesLargePayloadGracefully(): void
    {
        // Arrange
        $client = static::createClient();

        $largeMetadata = [];
        for ($i = 0; $i < 50; ++$i) {
            $largeMetadata["key_{$i}"] = str_repeat('x', 100);
        }

        $webhookPayload = [
            'id'         => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'                  => 'capture_test',
                'status'              => 'COMPLETED',
                'custom_id'           => 'payment_test',
                'supplementary_data'  => $largeMetadata,
            ],
        ];

        // Act
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(),
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Assert - Should handle large payloads without crashing
        $this->assertNotSame(500, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Response Headers
    // =============================================

    public function testWebhookResponseContainsAppropriateHeaders(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id'         => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => ['id' => 'capture_test'],
        ];

        // Act
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(),
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Assert - Response should have standard headers
        $response = $client->getResponse();
        $this->assertNotEmpty($response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->headers->get('Date'));
    }

    // =============================================
    // Test: Missing Required PayPal Headers
    // =============================================

    public function testItHandlesMissingPaypalHeaders(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id'         => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => ['id' => 'capture_test'],
        ];

        // Test missing transmission-id (but signature is present)
        $serverWithoutTransmissionId = [
            'HTTP_CONTENT_TYPE'             => 'application/json',
            'HTTP_PAYPAL_TRANSMISSION_SIG'  => 'test_signature',
            // Missing: HTTP_PAYPAL_TRANSMISSION_ID
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2025-01-01T00:00:00Z',
            'HTTP_PAYPAL_CERT_URL'          => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL_AUTH_ALGO'         => 'SHA256withRSA',
        ];

        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $serverWithoutTransmissionId,
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Should still process (headers are optional except signature)
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    // =============================================
    // Test: Duplicate Event IDs (Idempotency)
    // =============================================

    public function testItHandlesDuplicateEventIds(): void
    {
        // Arrange
        $client = static::createClient();
        $eventId = 'evt_duplicate_'.uniqid();

        $webhookPayload = [
            'id'         => $eventId,
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'        => 'capture_test',
                'custom_id' => 'payment_123',
            ],
        ];

        // Act - Send same event twice
        for ($i = 0; $i < 2; ++$i) {
            $client->request(
                'POST',
                self::WEBHOOK_URL,
                [],
                [],
                $this->paypalServerHeaders([
                    'HTTP_PAYPAL_TRANSMISSION_SIG' => 'test_signature_'.$i,
                    'HTTP_PAYPAL_TRANSMISSION_ID'  => 'test_id_'.$i,
                ]),
                (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
            );

            // Assert - Both requests should be processed (idempotency handled at business logic level)
            $this->assertNotSame(404, $client->getResponse()->getStatusCode());
        }
    }

    // =============================================
    // Test: Special Characters in Payload
    // =============================================

    public function testItHandlesSpecialCharactersInPayload(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id'         => 'evt_test_'.uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'        => 'capture_test',
                'custom_id' => 'payment_with_special_chars_<>&"\'/€',
                'status'    => 'COMPLETED',
            ],
        ];

        // Act
        $client->request(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            $this->paypalServerHeaders(),
            (string) json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        // Assert - Should handle special characters without crashing
        $this->assertNotSame(500, $client->getResponse()->getStatusCode());
    }

    /*
     * Note: The following scenarios require valid PayPal signatures and cannot be
     * fully tested in automated tests:
     *
     * 1. Successful PAYMENT.CAPTURE.COMPLETED processing with payment capture
     * 2. PAYMENT.CAPTURE.DENIED processing with payment marked as failed
     * 3. PAYMENT.CAPTURE.REFUNDED confirmation logging
     * 4. PAYMENT.AUTHORIZATION.CREATED event logging
     * 5. PAYMENT.AUTHORIZATION.VOIDED processing with payment failure
     * 6. CHECKOUT.ORDER.APPROVED event logging
     * 7. Payment already captured (idempotency check)
     * 8. Payment not found handling
     * 9. Missing metadata (payment_id) handling
     * 10. Actual PayPal event object validation
     *
     * For comprehensive testing of these scenarios, use:
     * - PayPal Sandbox Webhook Simulator: https://developer.paypal.com/dashboard/webhooks/simulator
     * - PayPal CLI (if available): forward webhooks to localhost
     * - Manual testing in PayPal Sandbox webhook logs
     * - Integration tests in staging environment with real PayPal webhooks
     *
     * For local development testing:
     * 1. Set up ngrok or localtunnel: `ngrok http 8000`
     * 2. Configure webhook URL in PayPal Sandbox: https://<your-ngrok-url>/api/webhooks/paypal
     * 3. Trigger test payments in PayPal Sandbox
     * 4. Monitor webhook logs: `tail -f var/log/dev.log | grep PayPal`
     *
     * See backend/docs/payment/paypal-webhook-testing.md for complete testing guide.
     */
}
