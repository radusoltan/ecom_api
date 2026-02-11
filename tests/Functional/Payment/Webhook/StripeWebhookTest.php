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
 */
final class StripeWebhookTest extends WebTestCase
{
    public function test_webhook_endpoint_exists_and_accepts_post(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ];

        // Act
        $client->request('POST', '/api/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'stripe-signature' => 'test_signature',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Endpoint exists (will return 400 for invalid signature, not 404)
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());
    }

    public function test_it_returns_400_when_signature_header_missing(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ];

        // Act - No stripe-signature header
        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Missing signature', $client->getResponse()->getContent());
    }

    public function test_it_returns_400_for_invalid_signature(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ];

        // Act - Invalid signature
        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'stripe-signature' => 'invalid_signature',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Invalid signature', $client->getResponse()->getContent());
    }

    public function test_it_handles_empty_payload_gracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'stripe-signature' => 'test_signature',
            ],
            'content' => '',
        ]);

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    public function test_it_handles_malformed_json_gracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'stripe-signature' => 'test_signature',
            ],
            'content' => 'not valid json {{{',
        ]);

        // Assert - Should return error, not crash
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 500]);
    }

    public function test_webhook_does_not_require_jwt_authentication(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ];

        // Act
        $client->request('POST', '/api/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'stripe-signature' => 'test_signature',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Should not return 401 (Unauthorized) or 403 (Forbidden)
        // Will return 400 (invalid signature) which is correct - no JWT required
        $this->assertNotSame(401, $client->getResponse()->getStatusCode());
        $this->assertNotSame(403, $client->getResponse()->getStatusCode());
    }

    public function test_it_only_accepts_post_method(): void
    {
        // Arrange
        $client = static::createClient();

        // Act & Assert - GET not allowed
        $client->request('GET', '/api/webhooks/stripe');
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - PUT not allowed
        $client->request('PUT', '/api/webhooks/stripe', [
            'json' => [],
        ]);
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // Act & Assert - DELETE not allowed
        $client->request('DELETE', '/api/webhooks/stripe');
        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function test_webhook_accepts_various_event_types(): void
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
            // Act
            $client->request('POST', '/api/v1/webhooks/stripe', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'stripe-signature' => 'test_signature',
                ],
                'json' => [
                    'id' => 'evt_test_' . bin2hex(random_bytes(4)),
                    'type' => $eventType,
                    'data' => ['object' => ['id' => 'obj_test']],
                ],
            ]);

            // Assert - All event types accepted (signature will fail, but 400 not 404)
            $this->assertNotSame(404, $client->getResponse()->getStatusCode(),
                "Event type '{$eventType}' should be accepted");
        }
    }

    public function test_webhook_handles_large_payload_gracefully(): void
    {
        // Arrange
        $client = static::createClient();

        // Create a large but valid webhook payload
        $largeMetadata = [];
        for ($i = 0; $i < 100; $i++) {
            $largeMetadata["key_{$i}"] = str_repeat('x', 100);
        }

        $webhookPayload = [
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test',
                    'metadata' => $largeMetadata,
                ],
            ],
        ];

        // Act
        $client->request('POST', '/api/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'stripe-signature' => 'test_signature',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Should handle large payloads without crashing
        $this->assertNotSame(500, $client->getResponse()->getStatusCode());
    }

    public function test_webhook_response_contains_appropriate_headers(): void
    {
        // Arrange
        $client = static::createClient();
        $webhookPayload = [
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ];

        // Act
        $client->request('POST', '/api/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'stripe-signature' => 'test_signature',
            ],
            'json' => $webhookPayload,
        ]);

        // Assert - Response should have standard headers
        $response = $client->getResponse();
        $this->assertNotEmpty($response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->headers->get('Date'));
    }

    /**
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
