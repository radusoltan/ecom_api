<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cart\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * Functional Tests for Checkout API Endpoint.
 *
 * Tests the checkout flow:
 * - POST /api/v1/checkout (Convert cart to order)
 *
 * Flow tested:
 * 1. Create a cart with items
 * 2. Call checkout with customer info and addresses
 * 3. Verify order is created
 * 4. Verify cart is marked as converted
 */
final class CheckoutApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private static int $counter = 0;
    private static ?string $defaultWarehouseId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up from previous tests
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();

        // Set RLS context
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );

        // Clear test data (orders.lines is a JSON column, not a separate table)
        $connection->executeStatement('DELETE FROM orders');
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
                self::$defaultWarehouseId = Uuid::v4()->toString();
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
        string $email = 'checkout-test@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER']
    ) {
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(Uuid::v4()->toString());
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

        return static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
            ],
        ]);
    }

    /**
     * Build headers array with X-Tenant-ID always included.
     */
    private function headers(array $extraHeaders = []): array
    {
        return array_merge([
            'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
        ], $extraHeaders);
    }

    /**
     * Create a product for testing using direct entity creation.
     */
    private function createProduct(int $price = 1999, int $stock = 100): string
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );

        $productId = Uuid::v7()->toString();

        $productEntity = new \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity();

        $reflection = new \ReflectionClass($productEntity);

        $idProp = $reflection->getProperty('id');
        $idProp->setValue($productEntity, $productId);

        $tenantIdProp = $reflection->getProperty('tenantId');
        $tenantIdProp->setValue($productEntity, self::DEFAULT_TENANT_ID);

        $skuProp = $reflection->getProperty('sku');
        $skuProp->setValue($productEntity, 'PRD-' . sprintf('%06d', random_int(100000, 999999)));

        $nameProp = $reflection->getProperty('name');
        $nameProp->setValue($productEntity, 'Test Product ' . ++self::$counter);

        $slugProp = $reflection->getProperty('slug');
        $slugProp->setValue($productEntity, 'test-product-' . uniqid());

        $priceProp = $reflection->getProperty('priceAmount');
        $priceProp->setValue($productEntity, $price);

        $currencyProp = $reflection->getProperty('priceCurrency');
        $currencyProp->setValue($productEntity, 'USD');

        $stockProp = $reflection->getProperty('stockQuantity');
        $stockProp->setValue($productEntity, $stock);

        $activeProp = $reflection->getProperty('active');
        $activeProp->setValue($productEntity, true);

        $createdProp = $reflection->getProperty('createdAt');
        $createdProp->setValue($productEntity, new \DateTimeImmutable());

        $updatedProp = $reflection->getProperty('updatedAt');
        $updatedProp->setValue($productEntity, new \DateTimeImmutable());

        $entityManager->persist($productEntity);
        $entityManager->flush();

        // Create stock item for this product (needed for cart stock validation)
        $stockItemId = Uuid::v4()->toString();
        $connection->executeStatement(
            "INSERT INTO stock_items (id, tenant_id, product_id, warehouse_id, on_hand, reserved, allocated, low_stock_threshold, created_at, updated_at)
             VALUES (:id, :tenantId, :productId, :warehouseId, :stock, 0, 0, 10, NOW(), NOW())",
            [
                'id' => $stockItemId,
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'warehouseId' => self::$defaultWarehouseId,
                'stock' => $stock,
            ]
        );

        return $productId;
    }

    /**
     * Helper to create a cart with items.
     *
     * @return array{cartId: string, items: array}
     */
    private function createCartWithItems(array $items): array
    {
        $client = $this->createAuthenticatedClient();

        $cartId = null;
        $cartItems = [];

        foreach ($items as $index => $item) {
            $productId = $item['productId'] ?? $this->createProduct($item['price'] ?? 1999);
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 1999;

            $payload = [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => $quantity,
                'unitPriceAmount' => $price,
                'unitPriceCurrency' => 'USD',
            ];

            $headers = [];
            if (null !== $cartId) {
                $headers = $this->headers(['X-Cart-ID' => $cartId]);
            }

            $response = $client->request('POST', '/api/v1/cart/items', [
                'json' => $payload,
                'headers' => $headers,
            ]);

            $data = json_decode($response->getContent(), true);
            $cartId = $data['id'];
            $cartItems = $data['items'];
        }

        return [
            'cartId' => $cartId,
            'items' => $cartItems,
        ];
    }

    // =============================================
    // Test: Successful Checkout
    // =============================================

    public function testItSuccessfullyChecksOutCart(): void
    {
        // Create a cart with items
        $cartData = $this->createCartWithItems([
            ['price' => 1999, 'quantity' => 2], // $19.99 x 2
            ['price' => 2999, 'quantity' => 1], // $29.99 x 1
        ]);

        $client = $this->createAuthenticatedClient();

        // Checkout
        $response = $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'customer@example.com',
                'shippingAddress' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postalCode' => '10001',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postalCode' => '10001',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Verify order was created
        $this->assertArrayHasKey('orderId', $data);
        $this->assertNotEmpty($data['orderId']);
        $this->assertEquals('pending', $data['status']);
        $this->assertEquals('customer@example.com', $data['customerEmail']);
        $this->assertEquals(6997, $data['totalAmount']); // (1999*2) + (2999*1) = 3998 + 2999 = 6997
        $this->assertEquals('USD', $data['totalCurrency']);
        $this->assertEquals(2, $data['itemCount']); // 2 unique order lines
    }

    public function testItChecksOutWithBillingAsShipping(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 1000, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'test@example.com',
                'billingAddress' => [
                    'street' => '456 Billing St',
                    'city' => 'Los Angeles',
                    'state' => 'CA',
                    'postalCode' => '90001',
                    'country' => 'US',
                ],
                'useBillingAsShipping' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('orderId', $data);
        $this->assertEquals('pending', $data['status']);
    }

    // =============================================
    // Test: Cart Marked as Converted
    // =============================================

    public function testItMarksCartAsConvertedAfterCheckout(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 500, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        // Checkout
        $response = $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'test@example.com',
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        // Try to retrieve the cart - should indicate it's converted (inactive)
        $cartResponse = $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cartData['cartId']]),
        ]);

        $cartResponseData = json_decode($cartResponse->getContent(), true);
        $this->assertEquals('converted', $cartResponseData['status']);
    }

    // =============================================
    // Test: Validation Errors
    // =============================================

    public function testItRejectsCheckoutWithoutCartId(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'customerEmail' => 'test@example.com',
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItRejectsCheckoutWithoutEmail(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 500, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItRejectsCheckoutWithInvalidEmail(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 500, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'not-an-email',
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItRejectsCheckoutWithoutBillingAddress(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 500, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'test@example.com',
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItRejectsCheckoutWithoutShippingAddressWhenNotUsingBillingAsShipping(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 500, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'test@example.com',
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'useBillingAsShipping' => false,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Cart Not Found
    // =============================================

    public function testItReturns404ForNonExistentCart(): void
    {
        $client = $this->createAuthenticatedClient();
        $fakeCartId = (string) new Ulid();

        $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $fakeCartId,
                'customerEmail' => 'test@example.com',
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // =============================================
    // Test: Empty Cart Checkout
    // =============================================

    public function testItRejectsCheckoutOfEmptyCart(): void
    {
        // Create a cart with item, then clear it
        $cartData = $this->createCartWithItems([
            ['price' => 500, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        // Clear the cart
        $client->request('DELETE', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cartData['cartId']]),
        ]);

        // Try to checkout empty cart
        $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'test@example.com',
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Double Checkout Prevention
    // =============================================

    public function testItPreventsDoubleCheckout(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 1000, 'quantity' => 1],
        ]);

        $client = $this->createAuthenticatedClient();

        $checkoutPayload = [
            'cartId' => $cartData['cartId'],
            'customerEmail' => 'test@example.com',
            'shippingAddress' => [
                'street' => '123 St',
                'city' => 'City',
                'state' => 'ST',
                'postalCode' => '12345',
                'country' => 'US',
            ],
            'billingAddress' => [
                'street' => '123 St',
                'city' => 'City',
                'state' => 'ST',
                'postalCode' => '12345',
                'country' => 'US',
            ],
        ];

        // First checkout - should succeed
        $response1 = $client->request('POST', '/api/v1/checkout', ['json' => $checkoutPayload]);
        $this->assertResponseStatusCodeSame(201);

        // Second checkout - should fail (cart is already converted)
        $client->request('POST', '/api/v1/checkout', ['json' => $checkoutPayload]);
        $this->assertResponseStatusCodeSame(409); // Conflict - cart already converted
    }

    // =============================================
    // Test: Multi-tenant Isolation
    // =============================================

    public function testCheckoutIsIsolatedByTenant(): void
    {
        $cartData = $this->createCartWithItems([
            ['price' => 1500, 'quantity' => 2],
        ]);

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'tenant-test@example.com',
                'shippingAddress' => [
                    'street' => '123 Tenant St',
                    'city' => 'Tenant City',
                    'state' => 'TC',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 Tenant St',
                    'city' => 'Tenant City',
                    'state' => 'TC',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Verify tenant ID matches
        $this->assertEquals(self::DEFAULT_TENANT_ID, $data['tenantId']);
    }

    // =============================================
    // Test: Order Lines Match Cart Items
    // =============================================

    public function testOrderLinesMatchCartItems(): void
    {
        $product1Id = $this->createProduct(1999);
        $product2Id = $this->createProduct(2999);

        $cartData = $this->createCartWithItems([
            ['productId' => $product1Id, 'price' => 1999, 'quantity' => 2],
            ['productId' => $product2Id, 'price' => 2999, 'quantity' => 3],
        ]);

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/checkout', [
            'json' => [
                'cartId' => $cartData['cartId'],
                'customerEmail' => 'lines-test@example.com',
                'shippingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
                'billingAddress' => [
                    'street' => '123 St',
                    'city' => 'City',
                    'state' => 'ST',
                    'postalCode' => '12345',
                    'country' => 'US',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Verify order lines
        $this->assertArrayHasKey('lines', $data);
        $this->assertCount(2, $data['lines']);

        // Total: (1999*2) + (2999*3) = 3998 + 8997 = 12995
        $this->assertEquals(12995, $data['totalAmount']);
        $this->assertEquals(2, $data['itemCount']); // 2 unique order lines
    }
}
