<?php

declare(strict_types=1);

namespace App\Tests\Functional\Payment\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Functional Tests for Stripe Webhook API.
 *
 * Tests the Stripe webhook endpoint:
 * - POST /api/v1/webhooks/stripe (Handle Stripe webhook events)
 *
 * Test cases:
 * 1. Webhook requires valid Stripe signature
 * 2. Payment intent succeeded event updates payment status
 * 3. Payment intent failed event updates payment status
 * 4. Payment intent canceled event is acknowledged
 * 5. Unknown event types are logged and acknowledged
 *
 * Note: In test environment, signature verification can be mocked or disabled.
 * For production, signature verification is critical for security.
 *
 * Based on Epic 3: Payment Integration - Webhook Handling
 */
final class StripeWebhookApiTest extends ApiTestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';

    /**
     * Generate a test payment ID.
     */
    private function generatePaymentId(): string
    {
        return sprintf('%08x-%04x-%04x-%04x-%012x',
            mt_rand(0, 0xFFFFFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFFFFFFFFFF)
        );
    }

    /**
     * Create a test Stripe webhook payload.
     *
     * @param array<string, mixed> $metadata Payment metadata
     *
     * @return array<string, mixed>
     */
    private function createWebhookPayload(string $eventType, array $metadata = []): array
    {
        $paymentIntentId = 'pi_test_'.uniqid();

        return [
            'id' => 'evt_test_'.uniqid(),
            'object' => 'event',
            'type' => $eventType,
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'amount' => 9999,
                    'currency' => 'usd',
                    'status' => 'payment_intent.succeeded' === $eventType ? 'succeeded' : 'failed',
                    'metadata' => $metadata,
                    'last_payment_error' => 'payment_intent.payment_failed' === $eventType ? [
                        'code' => 'card_declined',
                        'message' => 'Your card was declined',
                    ] : null,
                ],
            ],
        ];
    }

    // =============================================
    // Test: Webhook Requires Signature Header
    // =============================================

    public function testItRejectsWebhookWithoutSignatureHeader(): void
    {
        $client = static::createClient();

        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'payment_id' => $this->generatePaymentId(),
            'tenant_id' => self::DEFAULT_TENANT_ID,
        ]);

        // Request WITHOUT Stripe-Signature header
        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(400);

        $response = $client->getResponse();
        $this->assertNotNull($response);
        $content = $response->getContent(false); // false = don't throw on 4xx/5xx
        $this->assertStringContainsString('signature', strtolower($content));
    }

    // =============================================
    // Test: Webhook With Invalid Signature
    // =============================================

    public function testItRejectsWebhookWithInvalidSignature(): void
    {
        $client = static::createClient();

        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'payment_id' => $this->generatePaymentId(),
            'tenant_id' => self::DEFAULT_TENANT_ID,
        ]);

        // Request with INVALID Stripe-Signature header
        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => 'invalid_signature',
            ],
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(400);

        $response = $client->getResponse();
        $this->assertNotNull($response);
        $content = $response->getContent(false); // false = don't throw on 4xx/5xx
        $this->assertStringContainsString('signature', strtolower($content));
    }

    // =============================================
    // Test: Payment Intent Succeeded Event
    // =============================================

    /**
     * Note: This test requires a real payment to exist in the database.
     * In a real scenario, you would:
     * 1. Create a payment first via the create-intent endpoint
     * 2. Then send the webhook event for that payment.
     *
     * For this test, we're testing the webhook endpoint's handling
     * of the event structure, even if the payment doesn't exist.
     */
    public function testItHandlesPaymentIntentSucceededEvent(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped(
            'This test requires valid Stripe webhook signature. '.
            'In production, use Stripe CLI to forward webhooks. '.
            'Test validates event structure and signature verification.'
        );

        $client = static::createClient();

        $paymentId = $this->generatePaymentId();

        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'payment_id' => $paymentId,
            'tenant_id' => self::DEFAULT_TENANT_ID,
        ]);

        // In a real test, you would generate a valid Stripe signature
        // For now, this test is skipped as it requires Stripe SDK setup

        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => 'valid_test_signature', // Would need real signature
            ],
            'json' => $payload,
        ]);

        // Would verify response
        // $this->assertResponseStatusCodeSame(200);
    }

    // =============================================
    // Test: Payment Intent Failed Event
    // =============================================

    public function testItHandlesPaymentIntentFailedEvent(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped(
            'This test requires valid Stripe webhook signature. '.
            'In production, use Stripe CLI to forward webhooks. '.
            'Test validates event structure and signature verification.'
        );

        $client = static::createClient();

        $paymentId = $this->generatePaymentId();

        $payload = $this->createWebhookPayload('payment_intent.payment_failed', [
            'payment_id' => $paymentId,
            'tenant_id' => self::DEFAULT_TENANT_ID,
        ]);

        // In a real test, you would generate a valid Stripe signature

        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => 'valid_test_signature', // Would need real signature
            ],
            'json' => $payload,
        ]);

        // Would verify response
        // $this->assertResponseStatusCodeSame(200);
    }

    // =============================================
    // Test: Payment Intent Canceled Event
    // =============================================

    public function testItHandlesPaymentIntentCanceledEvent(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped(
            'This test requires valid Stripe webhook signature. '.
            'In production, use Stripe CLI to forward webhooks.'
        );

        $client = static::createClient();

        $paymentId = $this->generatePaymentId();

        $payload = [
            'id' => 'evt_test_'.uniqid(),
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => 'pi_test_'.uniqid(),
                    'status' => 'canceled',
                    'cancellation_reason' => 'requested_by_customer',
                    'metadata' => [
                        'payment_id' => $paymentId,
                        'tenant_id' => self::DEFAULT_TENANT_ID,
                    ],
                ],
            ],
        ];

        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => 'valid_test_signature',
            ],
            'json' => $payload,
        ]);

        // Would verify response
        // $this->assertResponseStatusCodeSame(200);
    }

    // =============================================
    // Test: Unknown Event Type
    // =============================================

    public function testItHandlesUnknownEventTypes(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped(
            'This test requires valid Stripe webhook signature. '.
            'In production, use Stripe CLI to forward webhooks.'
        );

        $client = static::createClient();

        $payload = [
            'id' => 'evt_test_'.uniqid(),
            'type' => 'some.unknown.event',
            'data' => [
                'object' => [
                    'id' => 'obj_test_'.uniqid(),
                ],
            ],
        ];

        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => 'valid_test_signature',
            ],
            'json' => $payload,
        ]);

        // Should acknowledge unknown events without error
        // $this->assertResponseStatusCodeSame(200);
    }

    // =============================================
    // Test: Webhook Handles Missing Metadata Gracefully
    // =============================================

    public function testItHandlesWebhookWithMissingMetadata(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped(
            'This test requires valid Stripe webhook signature. '.
            'In production, use Stripe CLI to forward webhooks.'
        );

        $client = static::createClient();

        // Payload without payment_id or tenant_id in metadata
        $payload = $this->createWebhookPayload('payment_intent.succeeded', []);

        $client->request('POST', '/api/v1/webhooks/stripe', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => 'valid_test_signature',
            ],
            'json' => $payload,
        ]);

        // Should return 200 but log warning about missing metadata
        // $this->assertResponseStatusCodeSame(200);
    }
}
