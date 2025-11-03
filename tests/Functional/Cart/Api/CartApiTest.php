<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cart\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Comprehensive Functional Tests for Cart API Endpoints
 *
 * Tests all 5 Cart API endpoints:
 * - GET /api/cart (Retrieve Cart)
 * - POST /api/cart/items (Add Item to Cart)
 * - PATCH /api/cart/items/{itemId} (Update Item Quantity)
 * - DELETE /api/cart/items/{itemId} (Remove Item)
 * - DELETE /api/cart (Clear Cart)
 *
 * Uses ApiTestCase with DAMA Bundle for automatic database transaction rollback.
 */
final class CartApiTest extends ApiTestCase
{
    private static int $counter = 0;
    private ?string $currentTenantId = null;
    private ?string $currentCartId = null;

    /**
     * Create an authenticated client with JWT token and optional X-Tenant-ID header
     */
    protected function createAuthenticatedClient(
        string $email = 'admin@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
        ?string $tenantId = null
    ) {
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0] . '-' . bin2hex(random_bytes(4)));
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

        $headers = ['authorization' => 'Bearer ' . $token];

        // Add X-Tenant-ID if provided or if we have a current tenant
        $tenantIdToUse = $tenantId ?? $this->currentTenantId;
        if ($tenantIdToUse !== null) {
            $headers['X-Tenant-ID'] = $tenantIdToUse;
        }

        return static::createClient([], ['headers' => $headers]);
    }

    /**
     * Generate a unique email address for testing
     */
    private function generateUniqueEmail(string $prefix = 'customer'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Create a valid tenant for testing
     */
    private function createTenant(): string
    {
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Test Tenant ' . uniqid(),
                'ownerEmail' => $this->generateUniqueEmail('tenant'),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        return $data['id'];
    }

    /**
     * Create a product for testing
     */
    private function createProduct(string $tenantId): string
    {
        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Use reflection to create product entity (no setters available)
        $container = $client->getContainer();
        $productId = \Symfony\Component\Uid\Ulid::generate();

        $entityManager = $container->get('doctrine')->getManager();
        $productEntity = new \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity();

        // Use reflection to set private properties
        $reflection = new \ReflectionClass($productEntity);

        $idProp = $reflection->getProperty('id');
        $idProp->setValue($productEntity, $productId);

        $tenantIdProp = $reflection->getProperty('tenantId');
        $tenantIdProp->setValue($productEntity, $tenantId);

        $skuProp = $reflection->getProperty('sku');
        $skuProp->setValue($productEntity, 'TEST-' . uniqid());

        $nameProp = $reflection->getProperty('name');
        $nameProp->setValue($productEntity, 'Test Product');

        $slugProp = $reflection->getProperty('slug');
        $slugProp->setValue($productEntity, 'test-product-' . uniqid());

        $priceProp = $reflection->getProperty('priceAmount');
        $priceProp->setValue($productEntity, 1999);

        $currencyProp = $reflection->getProperty('priceCurrency');
        $currencyProp->setValue($productEntity, 'USD');

        $stockProp = $reflection->getProperty('stockQuantity');
        $stockProp->setValue($productEntity, 100);

        $activeProp = $reflection->getProperty('active');
        $activeProp->setValue($productEntity, true);

        $createdProp = $reflection->getProperty('createdAt');
        $createdProp->setValue($productEntity, new \DateTimeImmutable());

        $updatedProp = $reflection->getProperty('updatedAt');
        $updatedProp->setValue($productEntity, new \DateTimeImmutable());

        $entityManager->persist($productEntity);
        $entityManager->flush();

        return $productId;
    }

    // =============================================
    // Test: Add Item to Cart
    // =============================================

    public function testItAddsItemToCart(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertEquals(2, $data['items'][0]['quantity']);
        $this->assertEquals($productId, $data['items'][0]['productId']);
        $this->assertEquals(2, $data['itemCount']);

        // Store cart ID for subsequent tests
        $this->currentCartId = $data['id'];
    }

    public function testItAddsItemWithExplicitPrice(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 1,
                'unitPriceAmount' => 2999, // $29.99
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertEquals(2999, $data['items'][0]['unitPrice']['amount']);
        $this->assertEquals(2999, $data['totalAmount']); // 1 * 2999
    }

    public function testItMergesQuantitiesWhenAddingSameProduct(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Add same product twice
        $response1 = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data1 = json_decode($response1->getContent(), true);
        $this->assertCount(1, $data1['items']);
        $this->assertEquals(2, $data1['items'][0]['quantity']);

        // Add same product again
        $response2 = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 3,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data2 = json_decode($response2->getContent(), true);

        // Should still have 1 item, but with merged quantity
        $this->assertCount(1, $data2['items']);
        $this->assertEquals(5, $data2['items'][0]['quantity']); // 2 + 3 = 5
    }

    public function testItFailsToAddItemWithoutProductId(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItFailsToAddItemWithInvalidQuantity(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 0, // Invalid: must be at least 1
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Get Cart
    // =============================================

    public function testItRetrievesCart(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // First add an item to create a cart
        $addResponse = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];

        // Now retrieve the cart
        $getResponse = $client->request('GET', '/api/v1/cart', [
            'headers' => [
                'X-Cart-ID' => $cartId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $getData = json_decode($getResponse->getContent(), true);

        $this->assertEquals($cartId, $getData['id']);
        $this->assertCount(1, $getData['items']);
        $this->assertEquals(2, $getData['itemCount']);
    }

    // =============================================
    // Test: Update Cart Item Quantity
    // =============================================

    public function testItUpdatesItemQuantity(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Add item
        $addResponse = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];
        $itemId = $addData['items'][0]['id'];

        // Update quantity
        $updateResponse = $client->request('PATCH', "/api/v1/cart/items/{$itemId}", [
            'json' => [
                'tenantId' => $tenantId,
                'newQuantity' => 5,
            ],
            'headers' => [
                'X-Cart-ID' => $cartId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $updateData = json_decode($updateResponse->getContent(), true);

        $this->assertEquals(5, $updateData['items'][0]['quantity']);
        $this->assertEquals(5, $updateData['itemCount']);
    }

    public function testItFailsToUpdateWithInvalidQuantity(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Add item
        $addResponse = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];
        $itemId = $addData['items'][0]['id'];

        // Try to update with invalid quantity
        $client->request('PATCH', "/api/v1/cart/items/{$itemId}", [
            'json' => [
                'tenantId' => $tenantId,
                'newQuantity' => 0, // Invalid
            ],
            'headers' => [
                'X-Cart-ID' => $cartId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Remove Item from Cart
    // =============================================

    public function testItRemovesItemFromCart(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Add item
        $addResponse = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];
        $itemId = $addData['items'][0]['id'];

        // Remove item
        $removeResponse = $client->request('DELETE', "/api/v1/cart/items/{$itemId}", [
            'headers' => [
                'X-Cart-ID' => $cartId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $removeData = json_decode($removeResponse->getContent(), true);

        $this->assertCount(0, $removeData['items']);
        $this->assertEquals(0, $removeData['itemCount']);
    }

    public function testItFailsToRemoveNonExistentItem(): void
    {
        $tenantId = $this->createTenant();
        $productId = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Add item to create cart
        $addResponse = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];

        // Try to remove non-existent item
        $fakeItemId = \Symfony\Component\Uid\Ulid::generate();

        $client->request('DELETE', "/api/v1/cart/items/{$fakeItemId}", [
            'headers' => [
                'X-Cart-ID' => $cartId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // =============================================
    // Test: Clear Cart
    // =============================================

    public function testItClearsCart(): void
    {
        $tenantId = $this->createTenant();
        $productId1 = $this->createProduct($tenantId);
        $productId2 = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Add multiple items
        $add1 = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId1,
                'quantity' => 2,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data1 = json_decode($add1->getContent(), true);
        $cartId = $data1['id'];

        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId2,
                'quantity' => 3,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Clear cart
        $clearResponse = $client->request('DELETE', '/api/v1/cart', [
            'headers' => [
                'X-Cart-ID' => $cartId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $clearData = json_decode($clearResponse->getContent(), true);

        $this->assertCount(0, $clearData['items']);
        $this->assertEquals(0, $clearData['itemCount']);
        $this->assertEquals(0, $clearData['totalAmount']);
    }

    // =============================================
    // Test: Cart Total Calculation
    // =============================================

    public function testItCalculatesCartTotalCorrectly(): void
    {
        $tenantId = $this->createTenant();
        $productId1 = $this->createProduct($tenantId);
        $productId2 = $this->createProduct($tenantId);
        $this->currentTenantId = $tenantId;

        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId);

        // Add item 1: 2 × $19.99 = $39.98
        $add1 = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId1,
                'quantity' => 2,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Add item 2: 1 × $19.99 = $19.99
        $add2 = $client->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenantId,
                'productId' => $productId2,
                'quantity' => 1,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($add2->getContent(), true);

        // Total: $39.98 + $19.99 = $59.97 (5997 cents)
        $this->assertEquals(5997, $data['totalAmount']);
        $this->assertEquals('USD', $data['totalCurrency']);
        $this->assertEquals(3, $data['itemCount']); // 2 + 1 = 3 items
    }

    // =============================================
    // Test: Multi-tenancy Isolation
    // =============================================

    public function testCartIsIsolatedByTenant(): void
    {
        $tenant1 = $this->createTenant();
        $tenant2 = $this->createTenant();

        $product1 = $this->createProduct($tenant1);
        $product2 = $this->createProduct($tenant2);

        $client1 = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenant1);
        $client2 = $this->createAuthenticatedClient('user@test.com', ['ROLE_USER'], $tenant2);

        // Create cart for tenant 1
        $response1 = $client1->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenant1,
                'productId' => $product1,
                'quantity' => 2,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data1 = json_decode($response1->getContent(), true);

        // Create cart for tenant 2
        $response2 = $client2->request('POST', '/api/cart/items', [
            'json' => [
                'tenantId' => $tenant2,
                'productId' => $product2,
                'quantity' => 3,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data2 = json_decode($response2->getContent(), true);

        // Carts should have different IDs
        $this->assertNotEquals($data1['id'], $data2['id']);

        // Each cart should only have 1 item
        $this->assertCount(1, $data1['items']);
        $this->assertCount(1, $data2['items']);
    }
}
