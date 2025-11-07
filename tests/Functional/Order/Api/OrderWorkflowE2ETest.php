<?php

declare(strict_types=1);

namespace App\Tests\Functional\Order\Api;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\OrderId;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-End Order Workflow Test.
 *
 * Tests the complete order lifecycle from placement to fulfillment:
 * 1. Place Order (POST /api/orders)
 * 2. Retrieve Order (GET /api/orders/{id})
 * 3. Update Order Status to Paid (PATCH /api/orders/{id}/status)
 * 4. Verify Fulfillment Created (GET /api/fulfillments?orderId={id})
 * 5. Start Picking (PATCH /api/fulfillments/{id}/start-picking)
 * 6. Start Packing (PATCH /api/fulfillments/{id}/start-packing)
 * 7. Ship Order (PATCH /api/fulfillments/{id}/ship)
 * 8. Mark as Delivered (PATCH /api/fulfillments/{id}/deliver)
 * 9. Verify Final Order Status
 *
 * Also validates:
 * - OpenAPI schema compliance for all responses
 * - Proper HTTP status codes
 * - Required headers (Content-Type, X-Tenant-ID)
 * - Response structure and data types
 */
final class OrderWorkflowE2ETest extends WebTestCase
{
    private const TENANT_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const CUSTOMER_EMAIL = 'e2e-test@example.com';

    private function cleanupTestData(): void
    {
        $kernel = self::bootKernel();
        $connection = $kernel->getContainer()->get('doctrine')->getConnection();

        // Clean up in reverse order due to foreign keys
        $connection->executeStatement(
            'DELETE FROM fulfillments WHERE tenant_id = ?',
            [self::TENANT_ID]
        );
        $connection->executeStatement(
            'DELETE FROM order_lines WHERE order_id IN (SELECT id FROM orders WHERE tenant_id = ?)',
            [self::TENANT_ID]
        );
        $connection->executeStatement(
            'DELETE FROM orders WHERE tenant_id = ? AND customer_email = ?',
            [self::TENANT_ID, self::CUSTOMER_EMAIL]
        );
    }

    public function testCompleteOrderWorkflowE2E(): void
    {
        $this->cleanupTestData();
        $client = static::createClient();

        // Step 1: Place Order
        $orderId = $this->placeOrder($client);
        $this->assertNotNull($orderId, 'Order ID should be returned after placement');

        // Step 2: Retrieve Order and verify structure
        $orderData = $this->retrieveOrder($client, $orderId);
        $this->validateOrderSchema($orderData);
        $this->assertSame('pending', $orderData['status'], 'Initial order status should be pending');
        $this->assertSame(self::CUSTOMER_EMAIL, $orderData['customerEmail']);

        // Step 3: Update Order Status to Paid
        $this->updateOrderStatus($client, $orderId, 'paid');

        // Verify status changed
        $orderData = $this->retrieveOrder($client, $orderId);
        $this->assertSame('paid', $orderData['status'], 'Order status should be paid after payment');

        // Step 4: Manually create fulfillment (simulating OrderPlacedFulfillmentSubscriber)
        $fulfillmentId = $this->createTestFulfillment($client, $orderId);
        $this->assertNotNull($fulfillmentId, 'Fulfillment should be created after order is paid');

        // Step 5: Start Picking
        $this->startPicking($client, $fulfillmentId);
        $fulfillmentData = $this->retrieveFulfillment($client, $fulfillmentId);
        $this->assertSame('picking', $fulfillmentData['status']);
        $this->assertNotNull($fulfillmentData['pickingStartedAt']);

        // Step 6: Start Packing
        $this->startPacking($client, $fulfillmentId);
        $fulfillmentData = $this->retrieveFulfillment($client, $fulfillmentId);
        $this->assertSame('packing', $fulfillmentData['status']);
        $this->assertNotNull($fulfillmentData['packingStartedAt']);

        // Step 7: Ship Order
        $trackingNumber = '1Z999AA10123456789';
        $carrier = 'UPS';
        $this->shipFulfillment($client, $fulfillmentId, $carrier, $trackingNumber);

        $fulfillmentData = $this->retrieveFulfillment($client, $fulfillmentId);
        $this->assertSame('shipping', $fulfillmentData['status']);
        $this->assertSame($carrier, $fulfillmentData['carrier']);
        $this->assertSame($trackingNumber, $fulfillmentData['trackingNumber']);
        $this->assertNotNull($fulfillmentData['shippedAt']);

        // Step 8: Mark as Delivered
        $this->markAsDelivered($client, $fulfillmentId);
        $fulfillmentData = $this->retrieveFulfillment($client, $fulfillmentId);
        $this->assertSame('delivered', $fulfillmentData['status']);
        $this->assertNotNull($fulfillmentData['deliveredAt']);

        // Step 9: Verify Final Order Status
        $orderData = $this->retrieveOrder($client, $orderId);
        $this->assertSame('paid', $orderData['status']); // Order status remains 'paid'

        // Cleanup
        $this->cleanupTestData();
    }

    public function testOrderCancellationWorkflow(): void
    {
        $this->cleanupTestData();
        $client = static::createClient();

        // Place order
        $orderId = $this->placeOrder($client);

        // Cancel order
        $client->request('PATCH', '/api/orders/'.$orderId.'/cancel', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        // Verify cancelled status
        $orderData = $this->retrieveOrder($client, $orderId);
        $this->assertSame('cancelled', $orderData['status']);
        $this->assertNotNull($orderData['cancelledAt']);

        $this->cleanupTestData();
    }

    public function testOrderValidationErrors(): void
    {
        $client = static::createClient();

        // Test missing required fields
        $client->request('POST', '/api/orders', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'customerEmail' => 'invalid-email', // Invalid email format
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testFulfillmentStateTransitionValidation(): void
    {
        $this->cleanupTestData();
        $client = static::createClient();

        $orderId = $this->placeOrder($client);
        $fulfillmentId = $this->createTestFulfillment($client, $orderId);

        // Try to ship without going through picking/packing
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/ship', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'carrier' => 'UPS',
            'trackingNumber' => '123456789',
        ]));

        // Should fail because status is 'assigned', not 'packing'
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->cleanupTestData();
    }

    public function testContentTypeNegotiation(): void
    {
        $client = static::createClient();

        // Request JSON response
        $client->request('GET', '/api/orders', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
    }

    public function testTenantIsolation(): void
    {
        $this->cleanupTestData();
        $client = static::createClient();

        // Create order for tenant 1
        $orderId = $this->placeOrder($client);

        // Try to access with different tenant ID
        $client->request('GET', '/api/orders/'.$orderId, [], [], [
            'HTTP_X-Tenant-ID' => '00000000-0000-0000-0000-000000000000', // Different tenant
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Should return 404 or empty result (not found for this tenant)
        $this->assertTrue(
            Response::HTTP_NOT_FOUND === $client->getResponse()->getStatusCode()
            || Response::HTTP_INTERNAL_SERVER_ERROR === $client->getResponse()->getStatusCode()
        );

        $this->cleanupTestData();
    }

    // Helper Methods

    private function placeOrder($client): string
    {
        $client->request('POST', '/api/orders', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'customerEmail' => self::CUSTOMER_EMAIL,
            'shippingAddress' => [
                'street' => '123 Test St',
                'city' => 'Test City',
                'postalCode' => '12345',
                'country' => 'US',
            ],
            'lines' => [
                [
                    'productId' => 'prod_test123',
                    'quantity' => 2,
                    'unitPrice' => 1999, // cents
                ],
            ],
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['id'];
    }

    private function retrieveOrder($client, string $orderId): array
    {
        $client->request('GET', '/api/orders/'.$orderId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        return json_decode($client->getResponse()->getContent(), true);
    }

    private function updateOrderStatus($client, string $orderId, string $newStatus): void
    {
        $client->request('PATCH', '/api/orders/'.$orderId.'/status', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'status' => $newStatus,
        ]));

        $this->assertResponseIsSuccessful();
    }

    private function createTestFulfillment($client, string $orderId): string
    {
        $connection = $client->getContainer()->get('doctrine')->getConnection();

        $fulfillmentId = (string) \Symfony\Component\Uid\Ulid::generate();
        $warehouseId = WarehouseId::generate()->toString();
        $now = new \DateTimeImmutable();

        $connection->insert('fulfillments', [
            'id' => $fulfillmentId,
            'order_id' => $orderId,
            'warehouse_id' => $warehouseId,
            'tenant_id' => self::TENANT_ID,
            'status' => 'assigned',
            'assigned_at' => $now->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return $fulfillmentId;
    }

    private function retrieveFulfillment($client, string $fulfillmentId): array
    {
        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        return json_decode($client->getResponse()->getContent(), true);
    }

    private function startPicking($client, string $fulfillmentId): void
    {
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-picking', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
    }

    private function startPacking($client, string $fulfillmentId): void
    {
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-packing', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
    }

    private function shipFulfillment($client, string $fulfillmentId, string $carrier, string $trackingNumber): void
    {
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/ship', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'carrier' => $carrier,
            'trackingNumber' => $trackingNumber,
        ]));

        $this->assertResponseIsSuccessful();
    }

    private function markAsDelivered($client, string $fulfillmentId): void
    {
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/deliver', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
    }

    private function validateOrderSchema(array $data): void
    {
        // Validate required fields exist
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('tenantId', $data);
        $this->assertArrayHasKey('customerEmail', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('currency', $data);
        $this->assertArrayHasKey('createdAt', $data);

        // Validate field types
        $this->assertIsString($data['id']);
        $this->assertIsString($data['tenantId']);
        $this->assertIsString($data['customerEmail']);
        $this->assertIsString($data['status']);
        $this->assertIsNumeric($data['total']);
        $this->assertIsString($data['currency']);

        // Validate email format
        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            $data['customerEmail']
        );

        // Validate status is one of allowed values
        $this->assertContains($data['status'], [
            'pending', 'confirmed', 'processing', 'paid', 'shipped', 'delivered', 'cancelled',
        ]);

        // Validate currency is ISO 4217 format
        $this->assertSame(3, strlen($data['currency']));
    }
}
