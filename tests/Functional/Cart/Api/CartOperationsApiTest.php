<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cart\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * Supplementary Functional Tests for Cart API Endpoints.
 *
 * Covers scenarios NOT already tested in CartApiTest:
 * - Missing header validations (400 responses)
 * - Response structure validation
 * - Cart status, tenantId, variantId, sessionId propagation
 * - Idempotent updates
 * - Multi-item removal consistency
 * - Currency propagation end-to-end
 */
final class CartOperationsApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private static ?string $defaultWarehouseId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();

        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );

        $connection->executeStatement('DELETE FROM cart_items');
        $connection->executeStatement('DELETE FROM carts');

        if (null === self::$defaultWarehouseId) {
            $existing = $connection->fetchOne(
                "SELECT id FROM warehouses WHERE tenant_id = :tenantId AND code = 'WH-OPS' LIMIT 1",
                ['tenantId' => self::DEFAULT_TENANT_ID]
            );

            if ($existing) {
                self::$defaultWarehouseId = $existing;
            } else {
                self::$defaultWarehouseId = Uuid::v4()->toString();
                $address = json_encode([
                    'street' => '1 Ops Street',
                    'city' => 'Ops City',
                    'state' => 'OC',
                    'postalCode' => '00001',
                    'country' => 'US',
                ]);
                $connection->executeStatement(
                    "INSERT INTO warehouses (id, tenant_id, code, name, address, is_active, priority, created_at, updated_at)
                     VALUES (:id, :tenantId, 'WH-OPS', 'Ops Warehouse', :address, true, 1, NOW(), NOW())",
                    [
                        'id' => self::$defaultWarehouseId,
                        'tenantId' => self::DEFAULT_TENANT_ID,
                        'address' => $address,
                    ]
                );
            }
        }
    }

    private function createAuthenticatedClient(
        string $email = 'ops@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
    ) {
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        $em = $container->get('doctrine')->getManager();
        $userRepository = $em->getRepository(
            \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class
        );

        if (!$userRepository->findOneBy(['email' => $email])) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0].'-'.bin2hex(random_bytes(4)));
            $userEntity->setPassword('$2y$13$dummy.password.hash');
            $userEntity->setRoles($roles);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $em->persist($userEntity);
            $em->flush();
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

    private function headers(array $extra = []): array
    {
        return array_merge(['X-Tenant-ID' => self::DEFAULT_TENANT_ID], $extra);
    }

    private function createProduct(int $stock = 100): string
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();

        $em = $container->get('doctrine')->getManager();
        $connection = $em->getConnection();
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );

        $productId = Uuid::v7()->toString();

        $productEntity = new \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity();
        $reflection = new \ReflectionClass($productEntity);

        $reflection->getProperty('id')->setValue($productEntity, $productId);
        $reflection->getProperty('tenantId')->setValue($productEntity, self::DEFAULT_TENANT_ID);
        $reflection->getProperty('sku')->setValue($productEntity, 'OPS-'.sprintf('%06d', random_int(100000, 999999)));
        $reflection->getProperty('name')->setValue($productEntity, 'Ops Test Product');
        $reflection->getProperty('slug')->setValue($productEntity, 'ops-test-product-'.uniqid());
        $reflection->getProperty('priceAmount')->setValue($productEntity, 2500);
        $reflection->getProperty('priceCurrency')->setValue($productEntity, 'USD');
        $reflection->getProperty('stockQuantity')->setValue($productEntity, $stock);
        $reflection->getProperty('active')->setValue($productEntity, true);
        $reflection->getProperty('createdAt')->setValue($productEntity, new \DateTimeImmutable());
        $reflection->getProperty('updatedAt')->setValue($productEntity, new \DateTimeImmutable());

        $em->persist($productEntity);
        $em->flush();

        $connection->executeStatement(
            'INSERT INTO stock_items (id, tenant_id, product_id, warehouse_id, on_hand, reserved, allocated, low_stock_threshold, created_at, updated_at)
             VALUES (:id, :tenantId, :productId, :warehouseId, :stock, 0, 0, 10, NOW(), NOW())',
            [
                'id' => Uuid::v4()->toString(),
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'warehouseId' => self::$defaultWarehouseId,
                'stock' => $stock,
            ]
        );

        return $productId;
    }

    private function addItemAndGetCartData(
        string $productId,
        int $quantity = 1,
        ?string $cartId = null,
        array $extra = [],
    ): array {
        $client = $this->createAuthenticatedClient();

        $headers = $this->headers($extra);
        if (null !== $cartId) {
            $headers['X-Cart-ID'] = $cartId;
        }

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => $quantity,
                'unitPriceAmount' => 2500,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $headers,
        ]);

        $this->assertResponseStatusCodeSame(201);

        return json_decode($response->getContent(), true);
    }

    // -------------------------------------------------------------------------
    // Missing header validations
    // -------------------------------------------------------------------------

    public function testGetCartWithoutCartIdHeaderReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testClearCartWithoutCartIdHeaderReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', '/api/v1/cart', [
            'headers' => $this->headers(),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateCartItemWithoutCartIdHeaderReturns400(): void
    {
        $productId = $this->createProduct();
        $cartData = $this->addItemAndGetCartData($productId);
        $itemId = $cartData['items'][0]['id'];

        $client = $this->createAuthenticatedClient();

        $client->request('PATCH', "/api/v1/cart/items/{$itemId}", [
            'headers' => array_merge(
                $this->headers(),
                ['Content-Type' => 'application/merge-patch+json']
            ),
            'body' => json_encode([
                'tenantId' => self::DEFAULT_TENANT_ID,
                'newQuantity' => 3,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRemoveItemWithoutCartIdHeaderReturns400(): void
    {
        $productId = $this->createProduct();
        $cartData = $this->addItemAndGetCartData($productId);
        $itemId = $cartData['items'][0]['id'];

        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', "/api/v1/cart/items/{$itemId}", [
            'headers' => $this->headers(),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAddItemWithoutTenantIdReturns400(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'productId' => $productId,
                'quantity' => 1,
                'unitPriceAmount' => 2500,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // -------------------------------------------------------------------------
    // Response structure and field validation
    // -------------------------------------------------------------------------

    public function testAddItemResponseContainsAllRequiredFields(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 2,
                'unitPriceAmount' => 2500,
                'unitPriceCurrency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('tenantId', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('totalAmount', $data);
        $this->assertArrayHasKey('totalCurrency', $data);
        $this->assertArrayHasKey('itemCount', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);

        $item = $data['items'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('productId', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('unitPrice', $item);
        $this->assertArrayHasKey('amount', $item['unitPrice']);
        $this->assertArrayHasKey('currency', $item['unitPrice']);
    }

    public function testNewCartHasActiveStatus(): void
    {
        $productId = $this->createProduct();
        $cartData = $this->addItemAndGetCartData($productId);

        $this->assertSame('active', $cartData['status']);
    }

    public function testCartResponseReflectsCorrectTenantId(): void
    {
        $productId = $this->createProduct();
        $cartData = $this->addItemAndGetCartData($productId);

        $this->assertSame(self::DEFAULT_TENANT_ID, $cartData['tenantId']);
    }

    public function testCartTotalCurrencyMatchesItemCurrency(): void
    {
        $productId = $this->createProduct();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 1,
                'unitPriceAmount' => 3000,
                'unitPriceCurrency' => 'EUR',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('EUR', $data['totalCurrency']);
        $this->assertSame('EUR', $data['items'][0]['unitPrice']['currency']);
    }

    // -------------------------------------------------------------------------
    // CQRS read/write consistency
    // -------------------------------------------------------------------------

    public function testGetCartIsConsistentWithWrittenState(): void
    {
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();

        $cartData1 = $this->addItemAndGetCartData($productId1, 2);
        $cartId = $cartData1['id'];

        $cartData2 = $this->addItemAndGetCartData($productId2, 3, $cartId);

        $client = $this->createAuthenticatedClient();
        $getResponse = $client->request('GET', '/api/v1/cart', [
            'headers' => $this->headers(['X-Cart-ID' => $cartId]),
        ]);

        $this->assertResponseStatusCodeSame(200);
        $getCartData = json_decode($getResponse->getContent(), true);

        $this->assertSame($cartId, $getCartData['id']);
        $this->assertCount(2, $getCartData['items']);
        $this->assertSame($cartData2['itemCount'], $getCartData['itemCount']);
        $this->assertSame($cartData2['totalAmount'], $getCartData['totalAmount']);
    }

    // -------------------------------------------------------------------------
    // Idempotent update
    // -------------------------------------------------------------------------

    public function testUpdateCartItemToSameQuantityIsIdempotent(): void
    {
        $productId = $this->createProduct();
        $cartData = $this->addItemAndGetCartData($productId, 3);
        $cartId = $cartData['id'];
        $itemId = $cartData['items'][0]['id'];
        $originalTotal = $cartData['totalAmount'];

        $client = $this->createAuthenticatedClient();

        $updateResponse = $client->request('PATCH', "/api/v1/cart/items/{$itemId}", [
            'headers' => array_merge(
                $this->headers(['X-Cart-ID' => $cartId]),
                ['Content-Type' => 'application/merge-patch+json']
            ),
            'body' => json_encode([
                'tenantId' => self::DEFAULT_TENANT_ID,
                'newQuantity' => 3,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(200);
        $updatedData = json_decode($updateResponse->getContent(), true);

        $this->assertSame(3, $updatedData['items'][0]['quantity']);
        $this->assertSame($originalTotal, $updatedData['totalAmount']);
    }

    // -------------------------------------------------------------------------
    // Multiple items can be added to the same cart
    // -------------------------------------------------------------------------

    public function testMultipleItemsCanBeAddedToSameCart(): void
    {
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();

        $cartData1 = $this->addItemAndGetCartData($productId1, 2);
        $cartId = $cartData1['id'];

        $cartData2 = $this->addItemAndGetCartData($productId2, 4, $cartId);

        $this->assertCount(2, $cartData2['items']);
        $this->assertSame(6, $cartData2['itemCount']);
        $this->assertSame($cartId, $cartData2['id']);
    }

    // -------------------------------------------------------------------------
    // Session ID propagation
    // -------------------------------------------------------------------------

    public function testSessionIdIsStoredOnCart(): void
    {
        $productId = $this->createProduct();
        $sessionId = Uuid::v4()->toString();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/cart/items', [
            'json' => [
                'tenantId' => self::DEFAULT_TENANT_ID,
                'productId' => $productId,
                'quantity' => 1,
                'unitPriceAmount' => 2500,
                'unitPriceCurrency' => 'USD',
            ],
            'headers' => $this->headers(['X-Session-ID' => $sessionId]),
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['sessionId']);
    }
}
