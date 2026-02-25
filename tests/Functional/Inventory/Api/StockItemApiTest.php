<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Doctrine\ORM\EntityManagerInterface;

final class StockItemApiTest extends ApiTestCase
{
    use TenantTestTrait;

    /**
     * Do not reboot the kernel on each createClient() call.
     *
     * With the default (null), every static::createClient() call triggers
     * static::bootKernel(), which creates a brand-new container and a brand-new
     * database connection.  That means the SET app.tenant_id executed in
     * setTenantContext() runs on connection A, while the Doctrine repository
     * injected from static::getContainer() uses connection B — so RLS sees no
     * tenant context and blocks every INSERT.
     *
     * Setting this to false keeps a single kernel/container/connection alive for
     * the entire test method, so setTenantContext(), the repository and the HTTP
     * client all share the same PostgreSQL session.
     */
    protected static ?bool $alwaysBootKernel = false;

    private TenantId $tenantId;
    private ProductId $productId;
    private WarehouseId $warehouseId;
    private StockItemRepositoryInterface $stockItemRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Boot the kernel once so that static::getContainer() is available.
        static::createClient();

        $this->tenantId = $this->getDefaultTenantId();
        $this->productId = ProductId::generate();
        $this->warehouseId = WarehouseId::generate();

        $container = static::getContainer();
        $this->stockItemRepository = $container->get(StockItemRepositoryInterface::class);

        // Set PostgreSQL session variable for RLS on the shared connection.
        // Must happen before any repository save() or raw SQL against tenant tables.
        $this->setTenantContext($this->tenantId->toString());

        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();

        parent::tearDown();
    }

    /**
     * Override TenantTestTrait::getEntityManager() to use the stable container
     * instead of calling static::createClient() (which would reboot the kernel
     * and open a new connection, losing the tenant context we just set).
     */
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function cleanupTestData(): void
    {
        $connection = $this->getEntityManager()->getConnection();

        // Re-establish tenant context: the connection is shared but a previous
        // test tearDown may have closed and reopened it.
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $this->tenantId->toString())
        );

        $connection->executeStatement(
            sprintf(
                "DELETE FROM stock_items WHERE tenant_id = '%s'",
                $this->tenantId->toString()
            )
        );
    }

    /**
     * Create an authenticated client with JWT token.
     *
     * Uses static::getContainer() directly (safe because $alwaysBootKernel = false
     * keeps a single kernel alive) to avoid rebooting the kernel and losing the
     * RLS tenant context set on the shared connection.
     */
    protected function createAuthenticatedClient(string $email = 'admin@admin.com', array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'])
    {
        $container = static::getContainer();

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
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);
    }

    public function testCreateStockItem(): void
    {
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock-items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'tenantId' => $this->tenantId->toString(),
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'initialQuantity' => 100,
                'lowStockThreshold' => 20,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($this->tenantId->toString(), $data['tenantId']);
        $this->assertEquals($this->productId->toString(), $data['productId']);
        $this->assertEquals($this->warehouseId->toString(), $data['warehouseId']);
        $this->assertEquals(100, $data['onHand']);
        $this->assertEquals(0, $data['reserved']);
        $this->assertEquals(0, $data['allocated']);
        $this->assertEquals(100, $data['available']);
        $this->assertEquals(20, $data['lowStockThreshold']);
        $this->assertFalse($data['isLowStock']);
    }

    public function testCreateStockItemWithDefaultThreshold(): void
    {
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock-items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'tenantId' => $this->tenantId->toString(),
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'initialQuantity' => 100,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals(10, $data['lowStockThreshold']); // Default threshold
    }

    public function testGetStockItemById(): void
    {
        $stockItem = $this->createStockItem(100, 20);

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/stock-items/'.$stockItem->id()->toString(), [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals($stockItem->id()->toString(), $data['id']);
        $this->assertEquals(100, $data['onHand']);
        $this->assertEquals(100, $data['available']);
    }

    public function testGetStockItemsByProductId(): void
    {
        $warehouse1 = WarehouseId::generate();
        $warehouse2 = WarehouseId::generate();

        $this->createStockItemInWarehouse($warehouse1, 100);
        $this->createStockItemInWarehouse($warehouse2, 50);

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/stock-items', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'productId' => $this->productId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
    }

    public function testGetStockItemsByWarehouseId(): void
    {
        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        $this->createStockItemForProduct($product1, 100);
        $this->createStockItemForProduct($product2, 50);

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/stock-items', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'warehouseId' => $this->warehouseId->toString(),
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
    }

    public function testCreateStockItemValidatesRequiredFields(): void
    {
        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock-items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'tenantId' => $this->tenantId->toString(),
                // Missing productId, warehouseId, initialQuantity
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateStockItemValidatesPositiveQuantity(): void
    {
        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock-items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'tenantId' => $this->tenantId->toString(),
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'initialQuantity' => -10,
                'lowStockThreshold' => 20,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetNonExistentStockItemReturns404(): void
    {
        $nonExistentId = StockItemId::generate();

        $this->createAuthenticatedClient()->request('GET', '/api/v1/stock-items/'.$nonExistentId->toString(), [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    private function createStockItem(int $quantity, int $threshold): StockItem
    {
        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt($quantity),
            Quantity::fromInt($threshold)
        );

        $this->stockItemRepository->save($stockItem);

        return $stockItem;
    }

    private function createStockItemInWarehouse(WarehouseId $warehouseId, int $quantity): StockItem
    {
        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $warehouseId,
            Quantity::fromInt($quantity)
        );

        $this->stockItemRepository->save($stockItem);

        return $stockItem;
    }

    private function createStockItemForProduct(ProductId $productId, int $quantity): StockItem
    {
        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $productId,
            $this->warehouseId,
            Quantity::fromInt($quantity)
        );

        $this->stockItemRepository->save($stockItem);

        return $stockItem;
    }
}
