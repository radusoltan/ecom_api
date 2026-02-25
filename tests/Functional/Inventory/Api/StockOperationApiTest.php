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
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Doctrine\ORM\EntityManagerInterface;

final class StockOperationApiTest extends ApiTestCase
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
    private StockReservationRepositoryInterface $reservationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the kernel is fresh — previous test classes may have left it
        // in a broken state (closed entity manager, stale connection, etc.).
        static::ensureKernelShutdown();
        static::createClient();

        $this->tenantId = $this->getDefaultTenantId();
        $this->productId = ProductId::generate();
        $this->warehouseId = WarehouseId::generate();

        $container = static::getContainer();

        // Ensure entity manager is open before retrieving repositories.
        $doctrine = $container->get('doctrine');
        $em = $doctrine->getManager();
        if (!$em->isOpen()) {
            $doctrine->resetManager();
        }

        $this->stockItemRepository = $container->get(StockItemRepositoryInterface::class);
        $this->reservationRepository = $container->get(StockReservationRepositoryInterface::class);

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
                "DELETE FROM stock_reservations WHERE tenant_id = '%s'",
                $this->tenantId->toString()
            )
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

    public function testReserveStock(): void
    {
        $stockItem = $this->createStockItem(100);
        $reservationId = 'cart-123';

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => $reservationId,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray();
        $this->assertEquals($this->productId->toString(), $data['productId']);
        $this->assertEquals($this->warehouseId->toString(), $data['warehouseId']);
        $this->assertEquals(10, $data['quantity']);
        $this->assertEquals($reservationId, $data['referenceId']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('Reserved', $data['message']);
        $this->assertEquals(90, $data['availableQuantity']); // 100 - 10 reserved
    }

    public function testReserveInsufficientStock(): void
    {
        $this->createStockItem(10);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 20,
                'referenceId' => 'cart-456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testReserveNonExistentProduct(): void
    {
        $nonExistentProduct = ProductId::generate();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $nonExistentProduct->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => 'cart-789',
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAllocateStock(): void
    {
        $stockItem = $this->createStockItem(100);
        $orderId = 'order-123';

        // First reserve
        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => $orderId,
            ],
        ]);

        // Then allocate
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/allocate', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => $orderId,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals($orderId, $data['referenceId']);
        $this->assertStringContainsString('Allocated', $data['message']);
        $this->assertEquals(90, $data['availableQuantity']); // 100 - 10 allocated
    }

    public function testAllocateWithoutReservation(): void
    {
        $this->createStockItem(100);
        $orderId = 'order-456';

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/allocate', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => $orderId,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals(90, $data['availableQuantity']);
    }

    public function testAllocateInsufficientStock(): void
    {
        $this->createStockItem(10);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/allocate', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 20,
                'referenceId' => 'order-789',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testReleaseStock(): void
    {
        $stockItem = $this->createStockItem(100);
        $reservationId = 'cart-123';

        // First reserve
        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => $reservationId,
            ],
        ]);

        // Then release
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/release', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => $reservationId,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertStringContainsString('Released', $data['message']);
        $this->assertEquals(100, $data['availableQuantity']); // Back to original
    }

    public function testReleaseAllocatedStock(): void
    {
        $stockItem = $this->createStockItem(100);
        $orderId = 'order-123';

        // Allocate
        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/allocate', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => $orderId,
            ],
        ]);

        // Release allocated stock (e.g., order cancelled)
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/release', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => sprintf('cancel-%s', $orderId),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(100, $data['availableQuantity']);
    }

    public function testMultipleReservationsForSameProduct(): void
    {
        $this->createStockItem(100);

        // First reservation
        $response1 = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 10,
                'referenceId' => 'cart-1',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data1 = $response1->toArray();
        $this->assertEquals(90, $data1['availableQuantity']);

        // Second reservation
        $response2 = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 15,
                'referenceId' => 'cart-2',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data2 = $response2->toArray();
        $this->assertEquals(75, $data2['availableQuantity']); // 100 - 10 - 15

        // Third reservation
        $response3 = $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 20,
                'referenceId' => 'cart-3',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data3 = $response3->toArray();
        $this->assertEquals(55, $data3['availableQuantity']); // 100 - 10 - 15 - 20
    }

    public function testReservationValidatesRequiredFields(): void
    {
        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                // Missing warehouseId, quantity, referenceId
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testStockOperationsValidatePositiveQuantity(): void
    {
        $this->createStockItem(100);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/stock/reserve', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => -10,
                'referenceId' => 'cart-999',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    private function createStockItem(int $quantity): StockItem
    {
        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt($quantity)
        );

        $this->stockItemRepository->save($stockItem);

        // Flush and clear the identity map so the HTTP handler's queries
        // always hit the database and find the freshly-persisted stock item.
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();
        $em->clear();

        return $stockItem;
    }
}
