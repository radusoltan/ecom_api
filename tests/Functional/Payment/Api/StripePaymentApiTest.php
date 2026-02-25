<?php

declare(strict_types=1);

namespace App\Tests\Functional\Payment\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Functional Tests for Stripe Payment API.
 *
 * Tests the Stripe payment integration endpoints:
 * - POST /api/v1/payments/stripe/create-intent (Create PaymentIntent)
 * - GET /api/v1/payments/stripe/verify-payment/{paymentIntentId} (Verify payment status)
 * - POST /api/v1/payments/stripe/capture-after-success/{paymentIntentId} (Capture payment)
 *
 * Test cases:
 * 1. Create payment intent with valid data
 * 2. Create payment intent requires orderId
 * 3. Create payment intent requires X-Tenant-ID header
 * 4. Create payment intent requires amount
 * 5. Create payment intent requires customerEmail
 * 6. Create payment intent with invalid amount (zero/negative)
 *
 * Based on Epic 3: Payment Integration
 */
final class StripePaymentApiTest extends ApiTestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private static int $counter = 0;

    /**
     * Generate a unique email address for testing.
     */
    private function generateUniqueEmail(string $prefix = 'test-payment'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Generate a unique order ID for testing.
     */
    private function generateUniqueOrderId(): string
    {
        return sprintf('test-order-%d-%s', ++self::$counter, substr(uniqid(), -8));
    }

    /**
     * Build headers array with X-Tenant-ID always included.
     *
     * For API Platform's test client, headers use kebab-case (X-Tenant-ID).
     * The HTTP_ prefix is NOT used with API Platform test client.
     *
     * @param array<string, string> $extraHeaders Additional headers to merge
     *
     * @return array<string, string>
     */
    private function headers(array $extraHeaders = []): array
    {
        return array_merge([
            'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
        ], $extraHeaders);
    }

    // =============================================
    // Test: Create Payment Intent - Success
    // =============================================

    public function testItCreatesPaymentIntentWithValidData(): void
    {
        $client = static::createClient();

        $orderId = $this->generateUniqueOrderId();
        $customerEmail = $this->generateUniqueEmail();

        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'headers' => $this->headers(),
            'json' => [
                'orderId' => $orderId,
                'amount' => 9999, // $99.99 in cents
                'currency' => 'usd',
                'customerEmail' => $customerEmail,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);

        // Verify response structure
        $this->assertArrayHasKey('clientSecret', $data);
        $this->assertArrayHasKey('paymentIntentId', $data);
        $this->assertArrayHasKey('paymentId', $data);

        // Verify clientSecret format (starts with "pi_" for test mode)
        $this->assertNotEmpty($data['clientSecret']);
        $this->assertStringStartsWith('pi_', $data['paymentIntentId']);

        // Verify paymentId is a valid UUID (8-4-4-4-12 hex format)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $data['paymentId']
        );
    }

    // =============================================
    // Test: Create Payment Intent - Missing OrderId
    // =============================================

    public function testItRejectsCreatePaymentIntentWithoutOrderId(): void
    {
        $client = static::createClient();

        $customerEmail = $this->generateUniqueEmail();

        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'headers' => $this->headers(),
            'json' => [
                // Missing orderId
                'amount' => 9999,
                'currency' => 'usd',
                'customerEmail' => $customerEmail,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        // Use toArray(false) to avoid exception on error responses
        $data = $response->toArray(false);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('orderId', $data['error']);
    }

    // =============================================
    // Test: Create Payment Intent - Missing Tenant ID
    // =============================================

    public function testItRejectsCreatePaymentIntentWithoutTenantId(): void
    {
        $client = static::createClient();

        $orderId = $this->generateUniqueOrderId();
        $customerEmail = $this->generateUniqueEmail();

        // Request WITHOUT X-Tenant-ID header
        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'json' => [
                'orderId' => $orderId,
                'amount' => 9999,
                'currency' => 'usd',
                'customerEmail' => $customerEmail,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        // Use toArray(false) to avoid exception on error responses
        $data = $response->toArray(false);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('X-Tenant-ID', $data['error']);
    }

    // =============================================
    // Test: Create Payment Intent - Missing Amount
    // =============================================

    public function testItRejectsCreatePaymentIntentWithoutAmount(): void
    {
        $client = static::createClient();

        $orderId = $this->generateUniqueOrderId();
        $customerEmail = $this->generateUniqueEmail();

        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'headers' => $this->headers(),
            'json' => [
                'orderId' => $orderId,
                // Missing amount
                'currency' => 'usd',
                'customerEmail' => $customerEmail,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        // Use toArray(false) to avoid exception on error responses
        $data = $response->toArray(false);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('amount', $data['error']);
    }

    // =============================================
    // Test: Create Payment Intent - Missing Customer Email
    // =============================================

    public function testItRejectsCreatePaymentIntentWithoutCustomerEmail(): void
    {
        $client = static::createClient();

        $orderId = $this->generateUniqueOrderId();

        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'headers' => $this->headers(),
            'json' => [
                'orderId' => $orderId,
                'amount' => 9999,
                'currency' => 'usd',
                // Missing customerEmail
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        // Use toArray(false) to avoid exception on error responses
        $data = $response->toArray(false);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('customerEmail', $data['error']);
    }

    // =============================================
    // Test: Create Payment Intent - Zero Amount
    // =============================================

    public function testItRejectsCreatePaymentIntentWithZeroAmount(): void
    {
        $client = static::createClient();

        $orderId = $this->generateUniqueOrderId();
        $customerEmail = $this->generateUniqueEmail();

        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'headers' => $this->headers(),
            'json' => [
                'orderId' => $orderId,
                'amount' => 0, // Invalid: zero amount
                'currency' => 'usd',
                'customerEmail' => $customerEmail,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        // Use toArray(false) to avoid exception on error responses
        $data = $response->toArray(false);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('amount', $data['error']);
    }

    // =============================================
    // Test: Create Payment Intent - Negative Amount
    // =============================================

    public function testItRejectsCreatePaymentIntentWithNegativeAmount(): void
    {
        $client = static::createClient();

        $orderId = $this->generateUniqueOrderId();
        $customerEmail = $this->generateUniqueEmail();

        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'headers' => $this->headers(),
            'json' => [
                'orderId' => $orderId,
                'amount' => -100, // Invalid: negative amount
                'currency' => 'usd',
                'customerEmail' => $customerEmail,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        // Use toArray(false) to avoid exception on error responses
        $data = $response->toArray(false);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('amount', $data['error']);
    }

    // =============================================
    // Test: Create Payment Intent - Default Currency (USD)
    // =============================================

    public function testItUsesDefaultCurrencyWhenNotProvided(): void
    {
        $client = static::createClient();

        $orderId = $this->generateUniqueOrderId();
        $customerEmail = $this->generateUniqueEmail();

        $response = $client->request('POST', '/api/v1/payments/stripe/create-intent', [
            'headers' => $this->headers(),
            'json' => [
                'orderId' => $orderId,
                'amount' => 5000,
                // currency not provided - should default to 'usd'
                'customerEmail' => $customerEmail,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);

        // Verify payment intent was created (we can't check currency directly in response
        // but if it's successful, the default 'usd' was used)
        $this->assertArrayHasKey('clientSecret', $data);
        $this->assertArrayHasKey('paymentIntentId', $data);
    }
}
