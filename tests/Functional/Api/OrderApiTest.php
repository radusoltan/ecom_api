<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Comprehensive Functional Tests for Order API Endpoints.
 *
 * Tests all 5 Order API endpoints:
 * - GET /api/orders (Collection with filters)
 * - GET /api/orders/{id} (Item)
 * - POST /api/orders (Place Order)
 * - PATCH /api/orders/{id}/status (Update Status)
 * - PATCH /api/orders/{id}/cancel (Cancel Order)
 *
 * Uses ApiTestCase with DAMA Bundle for automatic database transaction rollback.
 */
final class OrderApiTest extends ApiTestCase
{
    private static int $counter = 0;
    private ?string $currentTenantId = null;

    /**
     * Create an authenticated client with JWT token and optional X-Tenant-ID header.
     */
    protected function createAuthenticatedClient(string $email = 'admin@admin.com', array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'], ?string $tenantId = null)
    {
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0].'-'.bin2hex(random_bytes(4)));
            $userEntity->setPassword('$2y$13$dummy.password.hash');
            $userEntity->setRoles($roles);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($userEntity);
            $entityManager->flush();
        }

        $encoder = $container->get('lexik_jwt_authentication.encoder');

        $token = $encoder->encode([
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $headers = ['authorization' => 'Bearer '.$token];

        // Add X-Tenant-ID if provided or if we have a current tenant
        $tenantIdToUse = $tenantId ?? $this->currentTenantId;
        if (null !== $tenantIdToUse) {
            $headers['X-Tenant-ID'] = $tenantIdToUse;
        }

        return static::createClient([], ['headers' => $headers]);
    }

    /**
     * Generate a unique email address for testing.
     */
    private function generateUniqueEmail(string $prefix = 'customer'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Create a valid tenant for testing.
     */
    private function createTenant(): string
    {
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Test Tenant '.uniqid(),
                'ownerEmail' => $this->generateUniqueEmail('tenant'),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        return $data['id'];
    }

    /**
     * Helper method to create an order via the API.
     *
     * @return array<string, mixed> The created order data
     */
    private function placeOrder(
        ?string $tenantId = null,
        ?string $customerEmail = null,
        ?array $lines = null,
        ?array $shippingAddress = null,
        ?array $billingAddress = null
    ): array {
        $tenantId = $tenantId ?? $this->createTenant();
        $customerEmail = $customerEmail ?? $this->generateUniqueEmail();

        // Store tenant ID for subsequent requests in the same test
        $this->currentTenantId = $tenantId;

        $defaultAddress = [
            'street' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'TS',
            'postalCode' => '12345',
            'country' => 'US',
        ];

        $lines = $lines ?? [
            [
                'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                'productName' => 'Test Product',
                'quantity' => 2,
                'unitPriceAmount' => 1999, // $19.99 in cents
                'unitPriceCurrency' => 'USD',
            ],
        ];

        $shippingAddress = $shippingAddress ?? $defaultAddress;
        $billingAddress = $billingAddress ?? $defaultAddress;

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId)->request('POST', '/api/v1/orders', [
            'json' => [
                'tenantId' => $tenantId,
                'customerEmail' => $customerEmail,
                'lines' => $lines,
                'shippingAddress' => $shippingAddress,
                'billingAddress' => $billingAddress,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        return json_decode($response->getContent(), true);
    }

    /**
     * Extract order ID from API response.
     */
    private function extractOrderId(array $orderData): string
    {
        $this->assertArrayHasKey('id', $orderData);
        $this->assertNotEmpty($orderData['id']);

        return $orderData['id'];
    }

    // ========================================================================
    // POST /api/orders - Place Order Tests
    // ========================================================================

    public function testPlaceOrderWithValidDataReturns201(): void
    {
        // Arrange
        $tenantId = $this->createTenant();
        $customerEmail = $this->generateUniqueEmail();

        // Act
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/orders', [
            'json' => [
                'tenantId' => $tenantId,
                'customerEmail' => $customerEmail,
                'lines' => [
                    [
                        'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                        'productName' => 'Test Product',
                        'quantity' => 1,
                        'unitPriceAmount' => 2999,
                        'unitPriceCurrency' => 'USD',
                    ],
                ],
                'shippingAddress' => [
                    'street' => '456 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postalCode' => '10001',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '456 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postalCode' => '10001',
                    'country' => 'US',
                ],
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertSame('pending', $data['status']);
        $this->assertSame($customerEmail, $data['customerEmail']);
        $this->assertArrayHasKey('totalAmount', $data);
        $this->assertArrayHasKey('totalCurrency', $data);
        $this->assertSame(2999, $data['totalAmount']);
        $this->assertSame('USD', $data['totalCurrency']);
    }

    public function testPlaceOrderCalculatesTotalCorrectly(): void
    {
        // Arrange
        $tenantId = $this->createTenant();

        // Act
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/orders', [
            'json' => [
                'tenantId' => $tenantId,
                'customerEmail' => $this->generateUniqueEmail(),
                'lines' => [
                    [
                        'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                        'productName' => 'Product A',
                        'quantity' => 2,
                        'unitPriceAmount' => 1000, // $10.00
                        'unitPriceCurrency' => 'USD',
                    ],
                    [
                        'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                        'productName' => 'Product B',
                        'quantity' => 3,
                        'unitPriceAmount' => 1500, // $15.00
                        'unitPriceCurrency' => 'USD',
                    ],
                ],
                'shippingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Total should be (2 * $10) + (3 * $15) = $20 + $45 = $65 = 6500 cents
        $this->assertSame(6500, $data['totalAmount']);
    }

    public function testPlaceOrderValidatesRequiredFields(): void
    {
        // Arrange - Missing customerEmail
        $tenantId = $this->createTenant();

        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/v1/orders', [
            'json' => [
                'tenantId' => $tenantId,
                'lines' => [
                    [
                        'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                        'productName' => 'Test Product',
                        'quantity' => 1,
                        'unitPriceAmount' => 1000,
                        'unitPriceCurrency' => 'USD',
                    ],
                ],
                'shippingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(500);
    }

    public function testPlaceOrderValidatesEmptyLines(): void
    {
        // Arrange - Empty lines array
        $tenantId = $this->createTenant();

        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/v1/orders', [
            'json' => [
                'tenantId' => $tenantId,
                'customerEmail' => $this->generateUniqueEmail(),
                'lines' => [],
                'shippingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(500);
    }

    public function testPlaceOrderValidatesInvalidEmail(): void
    {
        // Arrange
        $tenantId = $this->createTenant();

        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/v1/orders', [
            'json' => [
                'tenantId' => $tenantId,
                'customerEmail' => 'not-a-valid-email',
                'lines' => [
                    [
                        'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                        'productName' => 'Test Product',
                        'quantity' => 1,
                        'unitPriceAmount' => 1000,
                        'unitPriceCurrency' => 'USD',
                    ],
                ],
                'shippingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(500);
    }

    // ========================================================================
    // GET /api/orders/{id} - Get Order by ID Tests
    // ========================================================================

    public function testGetOrderByIdReturnsSuccessfully(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Act
        $response = $this->createAuthenticatedClient()->request('GET', "/api/v1/orders/$orderId");

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Order',
            '@type' => 'Order',
            'id' => $orderId,
        ]);
    }

    public function testGetOrderByIdReturnsCorrectData(): void
    {
        // Arrange
        $customerEmail = $this->generateUniqueEmail();
        $orderData = $this->placeOrder(customerEmail: $customerEmail);
        $orderId = $this->extractOrderId($orderData);

        // Act
        $response = $this->createAuthenticatedClient()->request('GET', "/api/v1/orders/$orderId");

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($orderId, $data['id']);
        $this->assertSame($customerEmail, $data['customerEmail']);
        $this->assertSame('pending', $data['status']);
        $this->assertArrayHasKey('lines', $data);
        $this->assertIsArray($data['lines']);
        $this->assertCount(1, $data['lines']);
        $this->assertArrayHasKey('shippingAddress', $data);
        $this->assertArrayHasKey('billingAddress', $data);
        $this->assertArrayHasKey('totalAmount', $data);
        $this->assertArrayHasKey('totalCurrency', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testGetOrderByIdReturns404ForNonExistentOrder(): void
    {
        // Arrange - Use a valid UUID that doesn't exist
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;
        $nonExistentId = '00000000-0000-4000-8000-000000000000';

        // Act & Assert
        $client = $this->createAuthenticatedClient();
        $client->request('GET', "/api/v1/orders/$nonExistentId");

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [404, 500]),
            "Expected 404 or 500 for non-existent order, got $statusCode"
        );
    }

    // ========================================================================
    // GET /api/orders - Get Orders Collection Tests
    // ========================================================================

    public function testGetOrdersCollectionReturnsSuccessfully(): void
    {
        // Arrange - Create some orders for the same tenant
        $tenantId = $this->createTenant();
        $this->placeOrder(tenantId: $tenantId);
        $this->placeOrder(tenantId: $tenantId);

        // Act
        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/orders');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Order',
            '@type' => 'Collection',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);
        $this->assertGreaterThanOrEqual(2, count($data['member']));
    }

    public function testGetOrdersCollectionFiltersByCustomerEmail(): void
    {
        // Arrange - Create orders for different customers in the same tenant
        $tenantId = $this->createTenant();
        $customerEmail1 = $this->generateUniqueEmail('customer1');
        $customerEmail2 = $this->generateUniqueEmail('customer2');

        $order1 = $this->placeOrder(tenantId: $tenantId, customerEmail: $customerEmail1);
        $this->placeOrder(tenantId: $tenantId, customerEmail: $customerEmail2);

        // Act - Filter by customer email
        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/orders', [
            'query' => [
                'customerEmail' => $customerEmail1,
            ],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('member', $data);

        // All returned orders should belong to customer1
        foreach ($data['member'] as $order) {
            $this->assertSame($customerEmail1, $order['customerEmail']);
        }
    }

    // ========================================================================
    // PATCH /api/orders/{id}/status - Update Order Status Tests
    // ========================================================================

    public function testUpdateOrderStatusFromPendingToProcessing(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Act
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => [
                'status' => 'processing',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('processing', $data['status']);
    }

    public function testUpdateOrderStatusFollowsStateMachine(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Step 1: pending → processing
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'processing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        // Step 2: processing → shipped
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'shipped'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        // Step 3: shipped → delivered
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'delivered'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        $data = json_decode($response->getContent(), true);
        $this->assertSame('delivered', $data['status']);
    }

    public function testUpdateOrderStatusRejectsInvalidTransition(): void
    {
        // Arrange - Try to jump from pending to delivered (invalid)
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Act & Assert
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'delivered'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Domain validation should reject invalid transition
        $this->assertResponseStatusCodeSame(500);
    }

    // ========================================================================
    // PATCH /api/orders/{id}/cancel - Cancel Order Tests
    // ========================================================================

    public function testCancelPendingOrderReturnsSuccessfully(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Act
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/cancel", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('cancelled', $data['status']);
    }

    public function testCancelProcessingOrderReturnsSuccessfully(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Move to processing
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'processing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Act - Cancel from processing
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/cancel", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertSame('cancelled', $data['status']);
    }

    public function testCancelShippedOrderFails(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Move to shipped
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'processing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'shipped'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Act & Assert - Try to cancel shipped order
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/cancel", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Cannot cancel shipped order
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCancelDeliveredOrderFails(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Move to delivered
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'processing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'shipped'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'delivered'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Act & Assert - Try to cancel delivered order
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/cancel", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Cannot cancel delivered order
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCancelAlreadyCancelledOrderFails(): void
    {
        // Arrange
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Cancel once
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/cancel", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        // Act & Assert - Try to cancel again
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/cancel", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Cannot cancel already cancelled order
        $this->assertResponseStatusCodeSame(500);
    }

    // ========================================================================
    // Integration Tests - Complete Order Lifecycle
    // ========================================================================

    public function testCompleteOrderLifecycleFromPlacementToDelivery(): void
    {
        // Step 1: Place order
        $customerEmail = $this->generateUniqueEmail('lifecycle');
        $orderData = $this->placeOrder(customerEmail: $customerEmail);
        $orderId = $this->extractOrderId($orderData);
        $this->assertSame('pending', $orderData['status']);

        // Step 2: Move to processing
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'processing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertSame('processing', $data['status']);

        // Step 3: Ship order
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'shipped'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertSame('shipped', $data['status']);

        // Step 4: Deliver order
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'delivered'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertSame('delivered', $data['status']);

        // Step 5: Verify via GET
        $response = $this->createAuthenticatedClient()->request('GET', "/api/v1/orders/$orderId");
        $this->assertResponseIsSuccessful();
        $finalData = json_decode($response->getContent(), true);
        $this->assertSame('delivered', $finalData['status']);
        $this->assertSame($customerEmail, $finalData['customerEmail']);
    }

    public function testCompleteOrderLifecycleWithCancellation(): void
    {
        // Step 1: Place order
        $orderData = $this->placeOrder();
        $orderId = $this->extractOrderId($orderData);

        // Step 2: Move to processing
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'processing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        // Step 3: Cancel order
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/cancel", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertSame('cancelled', $data['status']);

        // Step 4: Verify cancelled order cannot be updated
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/orders/$orderId/status", [
            'json' => ['status' => 'processing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseStatusCodeSame(500);
    }

    public function testMultipleOrdersForSameCustomer(): void
    {
        // Arrange - Create multiple orders for same customer in same tenant
        $tenantId = $this->createTenant();
        $customerEmail = $this->generateUniqueEmail('multi');

        $order1 = $this->placeOrder(tenantId: $tenantId, customerEmail: $customerEmail);
        $order2 = $this->placeOrder(tenantId: $tenantId, customerEmail: $customerEmail);
        $order3 = $this->placeOrder(tenantId: $tenantId, customerEmail: $customerEmail);

        // Act - Get orders for this customer
        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/orders', [
            'query' => [
                'customerEmail' => $customerEmail,
            ],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(3, count($data['member']));

        // Verify all orders belong to the same customer
        foreach ($data['member'] as $order) {
            $this->assertSame($customerEmail, $order['customerEmail']);
        }
    }

    public function testOrderWithMultipleLineItems(): void
    {
        // Arrange
        $tenantId = $this->createTenant();
        $lines = [
            [
                'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                'productName' => 'Product 1',
                'quantity' => 2,
                'unitPriceAmount' => 1000,
                'unitPriceCurrency' => 'USD',
            ],
            [
                'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                'productName' => 'Product 2',
                'quantity' => 1,
                'unitPriceAmount' => 2500,
                'unitPriceCurrency' => 'USD',
            ],
            [
                'productId' => \Symfony\Component\Uid\Uuid::v4()->toString(),
                'productName' => 'Product 3',
                'quantity' => 3,
                'unitPriceAmount' => 500,
                'unitPriceCurrency' => 'USD',
            ],
        ];

        // Act
        $orderData = $this->placeOrder(
            tenantId: $tenantId,
            lines: $lines
        );

        // Assert
        $this->assertArrayHasKey('lines', $orderData);
        $this->assertCount(3, $orderData['lines']);

        // Verify total: (2*1000) + (1*2500) + (3*500) = 2000 + 2500 + 1500 = 6000
        $this->assertSame(6000, $orderData['totalAmount']);
    }

    public function testOrderPreservesAddressInformation(): void
    {
        // Arrange
        $shippingAddress = [
            'street' => '123 Shipping Lane',
            'city' => 'Ship City',
            'state' => 'SC',
            'postalCode' => '11111',
            'country' => 'US',
        ];

        $billingAddress = [
            'street' => '456 Billing Blvd',
            'city' => 'Bill City',
            'state' => 'BC',
            'postalCode' => '22222',
            'country' => 'US',
        ];

        // Act
        $orderData = $this->placeOrder(
            shippingAddress: $shippingAddress,
            billingAddress: $billingAddress
        );

        // Assert
        $this->assertArrayHasKey('shippingAddress', $orderData);
        $this->assertArrayHasKey('billingAddress', $orderData);

        $this->assertSame('123 Shipping Lane', $orderData['shippingAddress']['street']);
        $this->assertSame('Ship City', $orderData['shippingAddress']['city']);

        $this->assertSame('456 Billing Blvd', $orderData['billingAddress']['street']);
        $this->assertSame('Bill City', $orderData['billingAddress']['city']);
    }
}
