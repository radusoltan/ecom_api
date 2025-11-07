<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory\GraphQL;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * Functional tests for Stock Item GraphQL API.
 *
 * Tests GraphQL queries and mutations for stock items
 */
final class StockItemGraphQLTest extends ApiTestCase
{
    use TenantTestTrait;

    private TenantId $tenantId;
    private ProductId $productId;
    private WarehouseId $warehouseId;
    private StockItemId $stockItemId;

    protected function setUp(): void
    {
        parent::setUp();

        // Use default test tenant ID for RLS compatibility
        $this->tenantId = $this->getDefaultTenantId();
        $this->productId = ProductId::fromString(Uuid::v4()->toRfc4122());
        $this->warehouseId = WarehouseId::fromString((string) new Ulid());
        $this->stockItemId = StockItemId::fromString((string) new Ulid());

        // Set tenant context for direct DB operations
        $this->setTenantContext($this->tenantId->toString());

        // Clean up existing test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->cleanupTestData();

        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Delete all stock items for the test tenant
        $connection->executeStatement(
            sprintf(
                "DELETE FROM stock_items WHERE tenant_id = '%s'",
                $this->tenantId->toString()
            )
        );
    }

    protected function createAuthenticatedClient(
        string $email = 'admin@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER']
    ) {
        $container = static::getContainer();
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
                'content-type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);
    }

    public function testCreateStockItemMutation(): void
    {
        $client = $this->createAuthenticatedClient();

        $mutation = <<<'GRAPHQL'
mutation CreateStockItem($input: createStockItemInput!) {
  createStockItem(input: $input) {
    stockItem {
      id
      productId
      warehouseId
      onHand
      reserved
      allocated
      available
      lowStockThreshold
      isLowStock
    }
  }
}
GRAPHQL;

        $variables = [
            'input' => [
                'tenantId' => $this->tenantId->toString(),
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'initialQuantity' => 100,
                'lowStockThreshold' => 10,
            ],
        ];

        $client->request('POST', '/api/v1/graphql', [
            'json' => [
                'query' => $mutation,
                'variables' => $variables,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('createStockItem', $response['data']);
        $this->assertArrayHasKey('stockItem', $response['data']['createStockItem']);

        $stockItem = $response['data']['createStockItem']['stockItem'];
        $this->assertNotNull($stockItem['id']);
        $this->assertEquals($this->productId->toString(), $stockItem['productId']);
        $this->assertEquals($this->warehouseId->toString(), $stockItem['warehouseId']);
        $this->assertEquals(100, $stockItem['onHand']);
        $this->assertEquals(0, $stockItem['reserved']);
        $this->assertEquals(0, $stockItem['allocated']);
        $this->assertEquals(100, $stockItem['available']);
        $this->assertEquals(10, $stockItem['lowStockThreshold']);
        $this->assertFalse($stockItem['isLowStock']);
    }

    public function testQueryStockItemCollection(): void
    {
        $client = $this->createAuthenticatedClient();

        // First create a stock item
        $this->createStockItem($client);

        $query = <<<'GRAPHQL'
query GetStockItems {
  stockItems {
    edges {
      node {
        id
        productId
        warehouseId
        onHand
        available
      }
    }
    pageInfo {
      hasNextPage
      hasPreviousPage
    }
  }
}
GRAPHQL;

        $client->request('POST', '/api/v1/graphql', [
            'json' => [
                'query' => $query,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('stockItems', $response['data']);
        $this->assertArrayHasKey('edges', $response['data']['stockItems']);
        $this->assertNotEmpty($response['data']['stockItems']['edges']);
    }

    public function testQuerySingleStockItem(): void
    {
        $client = $this->createAuthenticatedClient();

        // First create a stock item
        $createdId = $this->createStockItem($client);

        $query = <<<'GRAPHQL'
query GetStockItem($id: ID!) {
  stockItem(id: $id) {
    id
    productId
    warehouseId
    onHand
    reserved
    allocated
    available
    lowStockThreshold
    isLowStock
  }
}
GRAPHQL;

        $variables = [
            'id' => '/api/stock-items/'.$createdId,
        ];

        $client->request('POST', '/api/v1/graphql', [
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('stockItem', $response['data']);

        $stockItem = $response['data']['stockItem'];
        $this->assertNotNull($stockItem);
        $this->assertEquals($this->productId->toString(), $stockItem['productId']);
        $this->assertEquals($this->warehouseId->toString(), $stockItem['warehouseId']);
    }

    public function testReserveStockMutation(): void
    {
        $client = $this->createAuthenticatedClient();

        // First create a stock item
        $this->createStockItem($client);

        $mutation = <<<'GRAPHQL'
mutation ReserveStock($input: reserveStockInput!) {
  reserveStock(input: $input) {
    stockOperation {
      productId
      warehouseId
      quantity
      message
      availableQuantity
    }
  }
}
GRAPHQL;

        $variables = [
            'input' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 20,
                'referenceId' => 'ORDER-12345',
            ],
        ];

        $client->request('POST', '/api/v1/graphql', [
            'json' => [
                'query' => $mutation,
                'variables' => $variables,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('reserveStock', $response['data']);
        $this->assertArrayHasKey('stockOperation', $response['data']['reserveStock']);

        $operation = $response['data']['reserveStock']['stockOperation'];
        $this->assertEquals($this->productId->toString(), $operation['productId']);
        $this->assertEquals($this->warehouseId->toString(), $operation['warehouseId']);
        $this->assertEquals(20, $operation['quantity']);
        $this->assertStringContainsString('reserved', strtolower($operation['message']));
        $this->assertEquals(80, $operation['availableQuantity']);
    }

    public function testAllocateStockMutation(): void
    {
        $client = $this->createAuthenticatedClient();

        // First create a stock item and reserve some quantity
        $this->createStockItem($client);
        $reservationId = 'RESERVATION-'.uniqid();
        $this->reserveStock($client, 30, $reservationId);

        $mutation = <<<'GRAPHQL'
mutation AllocateStock($input: allocateStockInput!) {
  allocateStock(input: $input) {
    stockOperation {
      productId
      warehouseId
      quantity
      message
      availableQuantity
    }
  }
}
GRAPHQL;

        $variables = [
            'input' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 30,
                'referenceId' => $reservationId,
            ],
        ];

        $client->request('POST', '/api/v1/graphql', [
            'json' => [
                'query' => $mutation,
                'variables' => $variables,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('allocateStock', $response['data']);

        $operation = $response['data']['allocateStock']['stockOperation'];
        $this->assertEquals(30, $operation['quantity']);
        $this->assertStringContainsString('allocated', strtolower($operation['message']));
    }

    public function testReleaseStockMutation(): void
    {
        $client = $this->createAuthenticatedClient();

        // First create a stock item and reserve some quantity
        $this->createStockItem($client);
        $reservationId = 'RESERVATION-'.uniqid();
        $this->reserveStock($client, 25, $reservationId);

        $mutation = <<<'GRAPHQL'
mutation ReleaseStock($input: releaseStockInput!) {
  releaseStock(input: $input) {
    stockOperation {
      productId
      warehouseId
      quantity
      message
      availableQuantity
    }
  }
}
GRAPHQL;

        $variables = [
            'input' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => 25,
                'referenceId' => $reservationId,
            ],
        ];

        $client->request('POST', '/api/v1/graphql', [
            'json' => [
                'query' => $mutation,
                'variables' => $variables,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('releaseStock', $response['data']);

        $operation = $response['data']['releaseStock']['stockOperation'];
        $this->assertEquals(25, $operation['quantity']);
        $this->assertStringContainsString('released', strtolower($operation['message']));
        $this->assertEquals(100, $operation['availableQuantity']);
    }

    public function testBulkReserveStockMutation(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create multiple stock items
        $productId1 = ProductId::fromString(Uuid::v4()->toRfc4122());
        $productId2 = ProductId::fromString(Uuid::v4()->toRfc4122());

        $this->createStockItemForProduct($client, $productId1, 100);
        $this->createStockItemForProduct($client, $productId2, 150);

        $mutation = <<<'GRAPHQL'
mutation BulkReserveStock($input: bulkReserveStockInput!) {
  bulkReserveStock(input: $input) {
    bulkStockOperation {
      referenceId
      totalItems
      successCount
      failureCount
      status
      results {
        productId
        warehouseId
        quantity
        success
        error
        availableQuantity
      }
    }
  }
}
GRAPHQL;

        $variables = [
            'input' => [
                'items' => [
                    [
                        'productId' => $productId1->toString(),
                        'warehouseId' => $this->warehouseId->toString(),
                        'quantity' => 50,
                    ],
                    [
                        'productId' => $productId2->toString(),
                        'warehouseId' => $this->warehouseId->toString(),
                        'quantity' => 75,
                    ],
                ],
                'referenceId' => 'BULK-ORDER-'.uniqid(),
            ],
        ];

        $client->request('POST', '/api/v1/graphql', [
            'json' => [
                'query' => $mutation,
                'variables' => $variables,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('bulkReserveStock', $response['data']);

        $bulkOp = $response['data']['bulkReserveStock']['bulkStockOperation'];
        $this->assertEquals(2, $bulkOp['totalItems']);
        $this->assertEquals(2, $bulkOp['successCount']);
        $this->assertEquals(0, $bulkOp['failureCount']);
        $this->assertEquals('success', $bulkOp['status']);
        $this->assertCount(2, $bulkOp['results']);
    }

    // Helper methods

    private function createStockItem($client): string
    {
        $client->request('POST', '/api/v1/stock-items', [
            'json' => [
                'tenantId' => $this->tenantId->toString(),
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'initialQuantity' => 100,
                'lowStockThreshold' => 10,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = $client->getResponse()->toArray();

        return $response['id'];
    }

    private function createStockItemForProduct($client, ProductId $productId, int $quantity): void
    {
        $client->request('POST', '/api/v1/stock-items', [
            'json' => [
                'tenantId' => $this->tenantId->toString(),
                'productId' => $productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'initialQuantity' => $quantity,
                'lowStockThreshold' => 10,
            ],
        ]);

        $this->assertResponseIsSuccessful();
    }

    private function reserveStock($client, int $quantity, string $referenceId): void
    {
        $client->request('POST', '/api/stock/reserve', [
            'json' => [
                'productId' => $this->productId->toString(),
                'warehouseId' => $this->warehouseId->toString(),
                'quantity' => $quantity,
                'referenceId' => $referenceId,
            ],
        ]);

        $this->assertResponseIsSuccessful();
    }
}
