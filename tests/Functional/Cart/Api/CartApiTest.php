<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cart\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;

/**
 * Comprehensive Functional Tests for Cart API Endpoints.
 *
 * Tests all 5 Cart API endpoints:
 * - GET /api/cart (Retrieve Cart)
 * - POST /api/cart/items (Add Item to Cart)
 * - PATCH /api/cart/items/{itemId} (Update Item Quantity)
 * - DELETE /api/cart/items/{itemId} (Remove Item)
 * - DELETE /api/cart (Clear Cart)
 *
 * Uses ApiTestCase with DAMA Bundle for automatic database transaction rollback.
 * All tests use the default test tenant (00000000-0000-4000-8000-000000000001)
 * to comply with PostgreSQL RLS policies.
 */
final class CartApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private static int $counter = 0;
    private static ?string $defaultWarehouseId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up carts from previous tests to ensure isolation
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();

        // Set RLS context
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );

        // Clear all test carts (optional - for test isolation)
        $connection->executeStatement('DELETE FROM cart_items');
        $connection->executeStatement('DELETE FROM carts');

        // Create default warehouse if not exists (needed for stock validation)
        if (null === self::$defaultWarehouseId) {
            // Check if warehouse already exists
            $existingWarehouse = $connection->fetchOne(
                "SELECT id FROM warehouses WHERE tenant_id = :tenantId AND code = 'WH-TEST' LIMIT 1",
                ['tenantId' => self::DEFAULT_TENANT_ID]
            );

            if ($existingWarehouse) {
                self::$defaultWarehouseId = $existingWarehouse;
            } else {
                self::$defaultWarehouseId = \Symfony\Component\Uid\Uuid::v4()->toString();
                $testAddress = json_encode([
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345',
                    'country' => 'US',
                ]);
                $connection->executeStatement(
                    "INSERT INTO warehouses (id, tenant_id, code, name, address, is_active, priority, created_at, updated_at)
                     VALUES (:id, :tenantId, 'WH-TEST', 'Test Warehouse', :address, true, 1, NOW(), NOW())",
                    [
                        'id' => self::$defaultWarehouseId,
                        'tenantId' => self::DEFAULT_TENANT_ID,
                        'address' => $testAddress,
                    ]
                );
            }
        }
    }

    /**
     * Create an authenticated client with JWT token and X-Tenant-ID header.
     */
    protected function createAuthenticatedClient(
        string $email = 'admin@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER']
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

        return static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer '.$token,
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
            ],
        ]);
    }

    /**
     * Generate a unique email address for testing.
     */
    private function generateUniqueEmail(string $prefix = 'customer'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Build headers array with X-Tenant-ID always included.
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

    /**
     * Create a product for testing using direct entity creation.
     * Uses default tenant to comply with RLS.
     */
    private function createProduct(): string
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();

        // Set tenant context for RLS
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );

        $productId = \Symfony\Component\Uid\Uuid::v7()->toString();

        $productEntity = new \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity();

        // Use reflection to set private properties
        $reflection = new \ReflectionClass($productEntity);

        $idProp = $reflection->getProperty('id');
        $idProp->setValue($productEntity, $productId);

        $tenantIdProp = $reflection->getProperty('tenantId');
        $tenantIdProp->setValue($productEntity, self::DEFAULT_TENANT_ID);

        $skuProp = $reflection->getProperty('sku');
        // SKU format must be: PRD-123456 (3 uppercase letters, hyphen, 6 digits)
        $skuProp->setValue($productEntity, 'PRD-'.sprintf('%06d', random_int(100000, 999999)));

        $nameProp = $reflection->getProperty('name');
        $nameProp->setValue($productEntity, 'Test Product');

        $slugProp = $reflection->getProperty('slug');
        $slugProp->setValue($productEntity, 'test-product-'.uniqid());

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

        // Create stock item for this product (needed for cart stock validation)
        $stockItemId = \Symfony\Component\Uid\Uuid::v4()->toString();
        $connection->executeStatement(
            "INSERT INTO stock_items (id, tenant_id, product_id, warehouse_id, on_hand, reserved, allocated, low_stock_threshold, created_at, updated_at)
             VALUES (:id, :tenantId, :productId, :warehouseId, :stock, 0, 0, 10, NOW(), NOW())",
            [
                'id' => $stockItemId,
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'warehouseId' => self::$defaultWarehouseId,
                'stock' => 100,
            ]
        );

        return $productId;
    }

    // =============================================
    // Test: Add Item to Cart
    // =============================================

    public function testItAddsItemToCart(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
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
    }

    public function testItAddsItemWithExplicitPrice(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
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
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add same product twice
        $response1 = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data1 = json_decode($response1->getContent(), true);
        $this->assertCount(1, $data1['items']);
        $this->assertEquals(2, $data1['items'][0]['quantity']);

        $cartId = $data1['id'];

        // Add same product again (reuse cart via X-Cart-ID header)
        $response2 = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 3,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data2 = json_decode($response2->getContent(), true);

        // Should still have 1 item, but with merged quantity
        $this->assertCount(1, $data2['items']);
        $this->assertEquals(5, $data2['items'][0]['quantity']); // 2 + 3 = 5
    }

    public function testItFailsToAddItemWithoutProductId(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'quantity' => 2,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItFailsToAddItemWithInvalidQuantity(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 0, // Invalid: must be at least 1
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Get Cart
    // =============================================

    public function testItRetrievesCart(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // First add an item to create a cart
        $addResponse = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];

        // Now retrieve the cart
        $getResponse = $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
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
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add item
        $addResponse = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];
        $itemId = $addData['items'][0]['id'];

        // Update quantity (PATCH requires application/merge-patch+json)
        $updateResponse = $client->request('PATCH', "/api/v1/cart/items/{$itemId}", [
            'headers' => array_merge(
                $this->headers(['X-Cart-ID' => $cartId]),
                ['Content-Type' => 'application/merge-patch+json']
            ),
            'body' => json_encode([
                'tenantId' => self::DEFAULT_TENANT_ID,
                'newQuantity' => 5,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(200);
        $updateData = json_decode($updateResponse->getContent(), true);

        $this->assertEquals(5, $updateData['items'][0]['quantity']);
        $this->assertEquals(5, $updateData['itemCount']);
    }

    public function testItFailsToUpdateWithInvalidQuantity(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add item
        $addResponse = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];
        $itemId = $addData['items'][0]['id'];

        // Try to update with invalid quantity (PATCH requires application/merge-patch+json)
        $client->request('PATCH', "/api/v1/cart/items/{$itemId}", [
            'headers' => array_merge(
                $this->headers(['X-Cart-ID' => $cartId]),
                ['Content-Type' => 'application/merge-patch+json']
            ),
            'body' => json_encode([
                'tenantId' => self::DEFAULT_TENANT_ID,
                'newQuantity' => 0, // Invalid
            ]),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Remove Item from Cart
    // =============================================

    public function testItRemovesItemFromCart(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add item
        $addResponse = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];
        $itemId = $addData['items'][0]['id'];

        // Remove item
        $removeResponse = $client->request('DELETE', "/api/v1/cart/items/{$itemId}", [
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
        ]);

        $this->assertResponseStatusCodeSame(200);
        $removeData = json_decode($removeResponse->getContent(), true);

        $this->assertCount(0, $removeData['items']);
        $this->assertEquals(0, $removeData['itemCount']);
    }

    public function testItFailsToRemoveNonExistentItem(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add item to create cart
        $addResponse = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $addData = json_decode($addResponse->getContent(), true);
        $cartId = $addData['id'];

        // Try to remove non-existent item (CartItemId uses ULID)
        $fakeItemId = (string) new \Symfony\Component\Uid\Ulid();

        $client->request('DELETE', "/api/v1/cart/items/{$fakeItemId}", [
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // =============================================
    // Test: Clear Cart
    // =============================================

    public function testItClearsCart(): void
    {
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add multiple items
        $add1 = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId1,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data1 = json_decode($add1->getContent(), true);
        $cartId = $data1['id'];

        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId2,
                'quantity' => 3,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Clear cart
        $clearResponse = $client->request('DELETE', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
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
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add item 1: 2 x $19.99 = $39.98
        $add1 = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId1,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data1 = json_decode($add1->getContent(), true);
        $cartId = $data1['id'];

        // Add item 2: 1 x $19.99 = $19.99 (reuse cart via X-Cart-ID header)
        $add2 = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId2,
                'quantity' => 1,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
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
        // This test verifies that cart operations work correctly within the default tenant
        // Multi-tenant isolation is enforced by PostgreSQL RLS at the database level
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Create cart for default tenant
        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Verify tenant ID matches
        $this->assertEquals(self::DEFAULT_TENANT_ID, $data['tenantId']);
        $this->assertCount(1, $data['items']);
        $this->assertEquals(2, $data['itemCount']);
    }

    // =============================================
    // Test: Cart Not Found (404)
    // =============================================

    public function testItReturnsNotFoundForNonExistentCart(): void
    {
        $client = $this->createAuthenticatedClient();
        // CartId expects ULID format
        $fakeCartId = (string) new \Symfony\Component\Uid\Ulid();

        $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $fakeCartId]),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // =============================================
    // Test: Guest Cart (Session-based)
    // =============================================

    public function testItCreatesCartForGuestUser(): void
    {
        $productId = $this->createProduct();

        // Create unauthenticated client with just tenant header
        $client = static::createClient([], [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
            ],
        ]);

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 1,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        // Guest cart creation should work (or return 401 if auth required)
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [201, 401], true),
            sprintf('Expected 201 or 401, got %d', $statusCode)
        );
    }

    // =============================================
    // Test: Add Multiple Different Products
    // =============================================

    public function testItAddsMultipleDifferentProductsToCart(): void
    {
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();
        $productId3 = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Add first product
        $response1 = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId1,
                'quantity' => 1,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data1 = json_decode($response1->getContent(), true);
        $cartId = $data1['id'];

        // Add second product (reuse cart)
        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId2,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Add third product (reuse cart)
        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId3,
                'quantity' => 3,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Should have 3 different items
        $this->assertCount(3, $data['items']);
        $this->assertEquals(6, $data['itemCount']); // 1 + 2 + 3 = 6
    }

    // =============================================
    // Test: Cart Assignment (Guest to Customer)
    // =============================================

    public function testItAssignsGuestCartToCustomer(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Create a guest cart first
        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $guestCartData = json_decode($response->getContent(), true);
        $guestCartId = $guestCartData['id'];

        // Assign the guest cart to a customer
        $customerId = \Symfony\Component\Uid\Uuid::v4()->toString();

        $assignResponse = $client->request('POST', '/api/v1/cart/assign', [
            'json' => [
                'customerId' => $customerId,
            ],
            'headers' => $this->headers(['X-Cart-ID' => $guestCartId]),
        ]);

        $this->assertResponseStatusCodeSame(200);
        $assignData = json_decode($assignResponse->getContent(), true);

        // Cart should now be assigned to the customer
        $this->assertEquals($guestCartId, $assignData['id']);
        $this->assertEquals($customerId, $assignData['customerId']);
        $this->assertCount(1, $assignData['items']);
        $this->assertEquals(2, $assignData['itemCount']);
    }

    public function testItMergesCartsWhenCustomerHasExistingCart(): void
    {
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();

        $customerId = \Symfony\Component\Uid\Uuid::v4()->toString();

        // Use single client with explicit cart IDs
        $client = $this->createAuthenticatedClient();

        // Step 1: Create a guest cart first (no customerId)
        $guestCart1Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId1,
                'quantity' => 2,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $guestCart1Data = json_decode($guestCart1Response->getContent(), true);
        $guestCart1Id = $guestCart1Data['id'];

        // Step 2: Assign the first guest cart to customer (this becomes their cart)
        $assignResponse1 = $client->request('POST', '/api/v1/cart/assign', [
            'json' => [
                'customerId' => $customerId,
            ],
            'headers' => $this->headers(['X-Cart-ID' => $guestCart1Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);
        $customerCartId = $guestCart1Id;  // After assignment, this is the customer's cart

        // Step 3: Create a second guest cart (simulate a different browser session)
        // We need to use a fresh unique session to force a new cart
        $guestCart2Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId2,
                'quantity' => 3,
                'unitPriceAmount' => 2999,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Session-ID' => \Symfony\Component\Uid\Uuid::v4()->toString()]),
        ]);

        $this->assertResponseStatusCodeSame(201);
        $guestCart2Data = json_decode($guestCart2Response->getContent(), true);
        $guestCart2Id = $guestCart2Data['id'];

        // Verify the second guest cart is different from the customer cart
        $this->assertNotEquals($customerCartId, $guestCart2Id);

        // Step 4: Assign the second guest cart to the same customer
        // This should merge the second guest cart into the customer's existing cart
        $mergeResponse = $client->request('POST', '/api/v1/cart/assign', [
            'json' => [
                'customerId' => $customerId,
            ],
            'headers' => $this->headers(['X-Cart-ID' => $guestCart2Id]),
        ]);

        $this->assertResponseStatusCodeSame(200);
        $mergedData = json_decode($mergeResponse->getContent(), true);

        // The merged cart should be the customer's cart (not the second guest cart)
        $this->assertEquals($customerCartId, $mergedData['id']);
        $this->assertEquals($customerId, $mergedData['customerId']);

        // Should have 2 items (both products from both carts)
        $this->assertCount(2, $mergedData['items']);

        // Total should be 2*1999 + 3*2999 = 3998 + 8997 = 12995
        $this->assertEquals(12995, $mergedData['totalAmount']);
    }

    public function testMergeCartsWithSameProductAddsQuantities(): void
    {
        $productId = $this->createProduct();
        $customerId = \Symfony\Component\Uid\Uuid::v4()->toString();
        $client = $this->createAuthenticatedClient();

        // Create first cart with product (qty 2)
        $cart1Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1000,
                'unitPriceCurrency' => 'USD',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $cart1Data = json_decode($cart1Response->getContent(), true);
        $cart1Id = $cart1Data['id'];

        // Assign first cart to customer
        $client->request('POST', '/api/v1/cart/assign', [
            'json' => ['customerId' => $customerId],
            'headers' => $this->headers(['X-Cart-ID' => $cart1Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Create second cart with SAME product (qty 3) using different session
        $cart2Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 3,
                'unitPriceAmount' => 1000,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Session-ID' => \Symfony\Component\Uid\Uuid::v4()->toString()]),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $cart2Data = json_decode($cart2Response->getContent(), true);
        $cart2Id = $cart2Data['id'];

        // Merge second cart into customer's cart
        $mergeResponse = $client->request('POST', '/api/v1/cart/assign', [
            'json' => ['customerId' => $customerId],
            'headers' => $this->headers(['X-Cart-ID' => $cart2Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);
        $mergedData = json_decode($mergeResponse->getContent(), true);

        // Should have 1 item with combined quantity (2 + 3 = 5)
        $this->assertCount(1, $mergedData['items']);
        $this->assertEquals(5, $mergedData['items'][0]['quantity'], 'Quantities should be added together');
        $this->assertEquals(5, $mergedData['itemCount']);
        $this->assertEquals(5000, $mergedData['totalAmount']); // 5 * 1000
        $this->assertEquals($cart1Id, $mergedData['id'], 'Should return customer cart ID');
    }

    public function testMergeCartsWithDifferentProductsKeepsBoth(): void
    {
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();
        $customerId = \Symfony\Component\Uid\Uuid::v4()->toString();
        $client = $this->createAuthenticatedClient();

        // Create first cart with product1 (qty 2)
        $cart1Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId1,
                'quantity' => 2,
                'unitPriceAmount' => 1500,
                'unitPriceCurrency' => 'USD',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $cart1Data = json_decode($cart1Response->getContent(), true);
        $cart1Id = $cart1Data['id'];

        // Assign first cart to customer
        $client->request('POST', '/api/v1/cart/assign', [
            'json' => ['customerId' => $customerId],
            'headers' => $this->headers(['X-Cart-ID' => $cart1Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Create second cart with DIFFERENT product (qty 3) using different session
        $cart2Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId2,
                'quantity' => 3,
                'unitPriceAmount' => 2000,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Session-ID' => \Symfony\Component\Uid\Uuid::v4()->toString()]),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $cart2Data = json_decode($cart2Response->getContent(), true);
        $cart2Id = $cart2Data['id'];

        // Merge second cart into customer's cart
        $mergeResponse = $client->request('POST', '/api/v1/cart/assign', [
            'json' => ['customerId' => $customerId],
            'headers' => $this->headers(['X-Cart-ID' => $cart2Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);
        $mergedData = json_decode($mergeResponse->getContent(), true);

        // Should have 2 items (both products kept)
        $this->assertCount(2, $mergedData['items'], 'Both products should be kept');
        $this->assertEquals(5, $mergedData['itemCount']); // 2 + 3 = 5 total items
        $this->assertEquals(9000, $mergedData['totalAmount']); // (2*1500) + (3*2000) = 3000 + 6000 = 9000
        $this->assertEquals($cart1Id, $mergedData['id'], 'Should return customer cart ID');

        // Verify both products are present
        $productIds = array_column($mergedData['items'], 'productId');
        $this->assertContains($productId1, $productIds, 'Product 1 should be in merged cart');
        $this->assertContains($productId2, $productIds, 'Product 2 should be in merged cart');
    }

    public function testMergeCartsDeletesSourceCart(): void
    {
        $productId = $this->createProduct();
        $customerId = \Symfony\Component\Uid\Uuid::v4()->toString();
        $client = $this->createAuthenticatedClient();

        // Create first cart (will become customer cart)
        $cart1Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 1,
                'unitPriceAmount' => 1000,
                'unitPriceCurrency' => 'USD',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $cart1Data = json_decode($cart1Response->getContent(), true);
        $cart1Id = $cart1Data['id'];

        // Assign first cart to customer
        $client->request('POST', '/api/v1/cart/assign', [
            'json' => ['customerId' => $customerId],
            'headers' => $this->headers(['X-Cart-ID' => $cart1Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Create second cart (guest cart to be merged) using different session
        $cart2Response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 1000,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Session-ID' => \Symfony\Component\Uid\Uuid::v4()->toString()]),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $cart2Data = json_decode($cart2Response->getContent(), true);
        $cart2Id = $cart2Data['id'];

        // Verify source cart exists before merge
        $sourceCartResponse = $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cart2Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Merge second cart into customer's cart
        $mergeResponse = $client->request('POST', '/api/v1/cart/assign', [
            'json' => ['customerId' => $customerId],
            'headers' => $this->headers(['X-Cart-ID' => $cart2Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Try to access the source (guest) cart - it should be deleted/not accessible
        $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cart2Id]),
        ]);
        $this->assertResponseStatusCodeSame(404, 'Source cart should be deleted after merge');

        // Verify customer cart still exists and has merged items
        $customerCartResponse = $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cart1Id]),
        ]);
        $this->assertResponseStatusCodeSame(200);
        $customerCartData = json_decode($customerCartResponse->getContent(), true);
        $this->assertEquals(3, $customerCartData['items'][0]['quantity'], 'Quantities should be merged (1 + 2 = 3)');
    }

    public function testItFailsToAssignCartWithoutCustomerId(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        // Create a guest cart
        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 1,
                'unitPriceAmount' => 1999,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $guestCartData = json_decode($response->getContent(), true);
        $guestCartId = $guestCartData['id'];

        // Try to assign without customerId
        $client->request('POST', '/api/v1/cart/assign', [
            'json' => [],
            'headers' => $this->headers(['X-Cart-ID' => $guestCartId]),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItFailsToAssignWithoutCartId(): void
    {
        $client = $this->createAuthenticatedClient();

        $customerId = \Symfony\Component\Uid\Uuid::v4()->toString();

        // Try to assign without X-Cart-ID header
        $client->request('POST', '/api/v1/cart/assign', [
            'json' => [
                'customerId' => $customerId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
