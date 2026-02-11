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
    // =============================================
    // Test: Endpoint Exists and Accepts POST
    // =============================================

    public function test_webhook_endpoint_exists_and_accepts_post(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Act
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                'paypal-transmission-id' => 'test_id',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Endpoint exists (will return 400 for invalid signature, not 404)
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Missing Signature Header
    // =============================================

    public function test_it_returns_400_when_signature_header_missing(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Act - No paypal-transmission-sig header
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Missing signature', $client->getResponse()->getContent());
    }

    // =============================================
    // Test: Invalid Signature
    // =============================================

    public function test_it_returns_400_for_invalid_signature(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_test',
                'status' => 'COMPLETED',
            ],
        ];

        // Act - Invalid signature (PayPal will reject verification)
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'invalid_signature_xyz',
                'paypal-transmission-id' => 'test_id_123',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Signature verification will fail
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Invalid signature', $client->getResponse()->getContent());
    }

    // =============================================
    // Test: Empty Payload
    // =============================================

    public function test_it_handles_empty_payload_gracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                'paypal-transmission-id' => 'test_id',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'content' => '',
        ]);

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    // =============================================
    // Test: Malformed JSON
    // =============================================

    public function test_it_handles_malformed_json_gracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                'paypal-transmission-id' => 'test_id',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'content' => '{not valid json [[[',
        ]);

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    // =============================================
    // Test: No JWT Authentication Required
    // =============================================

    public function test_webhook_does_not_require_jwt_authentication(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Act - No Authorization header
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                'paypal-transmission-id' => 'test_id',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Should not return 401 (Unauthorized) or 403 (Forbidden)
        // Will return 400 (invalid signature) which is correct - no JWT required
        $this->assertNotSame(401, $client->getResponse()->getStatusCode());
        $this->assertNotSame(403, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Only POST Method Allowed
    // =============================================

    public function test_it_only_accepts_post_method(): void
    {
        // Arrange
        $client = static::createClient();

        // Act & Assert - GET not allowed
        $client->request('GET', '/api/webhooks/paypal');
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - PUT not allowed
        $client->request('PUT', '/api/webhooks/paypal', [
            'json' => [],
        ]);
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - DELETE not allowed
        $client->request('DELETE', '/api/webhooks/paypal');
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - PATCH not allowed
        $client->request('PATCH', '/api/webhooks/paypal', [
            'json' => [],
        ]);
        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Various Event Types Accepted
    // =============================================

    public function test_webhook_accepts_various_event_types(): void
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
            $client->request('POST', '/api/webhooks/paypal', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'paypal-transmission-sig' => 'test_signature_' . bin2hex(random_bytes(4)),
                    'paypal-transmission-id' => 'test_id_' . uniqid(),
                    'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                    'paypal-cert-url' => 'https://api.paypal.com/cert',
                    'paypal-auth-algo' => 'SHA256withRSA',
                ],
                'json' => [
                    'id' => 'evt_test_' . bin2hex(random_bytes(4)),
                    'event_type' => $eventType,
                    'resource' => ['id' => 'obj_test_' . uniqid()],
                ],
            ]);

            // Assert - All event types accepted (signature will fail, but 400 not 404)
            $this->assertNotSame(404, $client->getResponse()->getStatusCode(),
                "Event type '{$eventType}' should be accepted");
        }
    }

    // =============================================
    // Test: Large Payload Handling
    // =============================================

    public function test_webhook_handles_large_payload_gracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Create a large but valid webhook payload
        $largeMetadata = [];
        for ($i = 0; $i < 50; $i++) {
            $largeMetadata["key_{$i}"] = str_repeat('x', 100);
        }

        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_test',
                'status' => 'COMPLETED',
                'custom_id' => 'payment_test',
                'supplementary_data' => $largeMetadata,
            ],
        ];

        // Act
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                'paypal-transmission-id' => 'test_id',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Should handle large payloads without crashing
        $this->assertNotSame(500, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // Test: Response Headers
    // =============================================

    public function test_webhook_response_contains_appropriate_headers(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Act
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                'paypal-transmission-id' => 'test_id',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Response should have standard headers
        $response = $client->getResponse();
        $this->assertNotEmpty($response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->headers->get('Date'));
    }

    // =============================================
    // Test: Missing Required PayPal Headers
    // =============================================

    public function test_it_handles_missing_paypal_headers(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'capture_test'],
        ];

        // Test missing transmission-id
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                // Missing: paypal-transmission-id
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'json' => $webhookPayload,
        ]);

        // Should still process (headers are optional except signature)
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    // =============================================
    // Test: Duplicate Event IDs (Idempotency)
    // =============================================

    public function test_it_handles_duplicate_event_ids(): void
    {
        // Arrange
        $client = static::createClient();
        $eventId = 'evt_duplicate_' . uniqid();

        $webhookPayload = [
            'id' => $eventId,
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_test',
                'custom_id' => 'payment_123',
            ],
        ];

        // Act - Send same event twice
        for ($i = 0; $i < 2; $i++) {
            $client->request('POST', '/api/webhooks/paypal', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'paypal-transmission-sig' => 'test_signature_' . $i,
                    'paypal-transmission-id' => 'test_id_' . $i,
                    'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                    'paypal-cert-url' => 'https://api.paypal.com/cert',
                    'paypal-auth-algo' => 'SHA256withRSA',
                ],
                'json' => $webhookPayload,
            ]);

            // Assert - Both requests should be processed (idempotency handled at business logic level)
            $this->assertNotSame(404, $client->getResponse()->getStatusCode());
        }
    }

    // =============================================
    // Test: Special Characters in Payload
    // =============================================

    public function test_it_handles_special_characters_in_payload(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test_' . uniqid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_test',
                'custom_id' => 'payment_with_special_chars_<>&"\'/€',
                'status' => 'COMPLETED',
            ],
        ];

        // Act
        $client->request('POST', '/api/webhooks/paypal', [
            'headers' => [
                'Content-Type' => 'application/json',
                'paypal-transmission-sig' => 'test_signature',
                'paypal-transmission-id' => 'test_id',
                'paypal-transmission-time' => '2025-01-01T00:00:00Z',
                'paypal-cert-url' => 'https://api.paypal.com/cert',
                'paypal-auth-algo' => 'SHA256withRSA',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Should handle special characters without crashing
        $this->assertNotSame(500, $client->getResponse()->getStatusCode());
    }

    /**
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
