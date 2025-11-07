<?php

declare(strict_types=1);

namespace App\Tests\Functional\Order\Api;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class FulfillmentApiTest extends WebTestCase
{
    private const TENANT_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    private function cleanupTestData(): void
    {
        $kernel = self::bootKernel();
        $connection = $kernel->getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('DELETE FROM fulfillments WHERE tenant_id = ?', [self::TENANT_ID]);
    }

    public function testGetFulfillmentCollection(): void
    {
        $this->cleanupTestData();
        $client = static::createClient();

        // Create test fulfillment
        $fulfillmentId = $this->createTestFulfillment($client);

        $client->request('GET', '/api/fulfillments', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $this->cleanupTestData();
    }

    public function testGetSingleFulfillment(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($fulfillmentId, $data['id']);
        $this->assertSame('assigned', $data['status']);
    }

    public function testStartPicking(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-picking', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        // Verify status changed
        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('picking', $data['status']);
        $this->assertNotNull($data['pickingStartedAt']);
    }

    public function testStartPacking(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        // Start picking first
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-picking', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Then start packing
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-packing', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        // Verify status changed
        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('packing', $data['status']);
        $this->assertNotNull($data['packingStartedAt']);
    }

    public function testShipWithTrackingInfo(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        // Progress to packing
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-picking', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
        ]);
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-packing', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
        ]);

        // Ship with tracking
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/ship', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'carrier' => 'UPS',
            'trackingNumber' => '1Z999AA10123456784',
        ]));

        $this->assertResponseIsSuccessful();

        // Verify tracking info
        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('shipping', $data['status']);
        $this->assertSame('UPS', $data['carrier']);
        $this->assertSame('1Z999AA10123456784', $data['trackingNumber']);
        $this->assertNotNull($data['shippedAt']);
    }

    public function testShipRequiresCarrierAndTrackingNumber(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        // Try to ship without tracking info
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/ship', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testMarkAsDelivered(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        // Progress to shipping
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-picking', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
        ]);
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-packing', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
        ]);
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/ship', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'carrier' => 'FedEx',
            'trackingNumber' => '123456789',
        ]));

        // Mark as delivered
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/deliver', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        // Verify status
        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('delivered', $data['status']);
        $this->assertNotNull($data['deliveredAt']);
    }

    public function testCancelFulfillment(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/cancel', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        // Verify status
        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('cancelled', $data['status']);
        $this->assertNotNull($data['cancelledAt']);
    }

    public function testMarkAsFailed(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/mark-failed', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Package damaged during transit',
        ]));

        $this->assertResponseIsSuccessful();

        // Verify status and reason
        $client->request('GET', '/api/fulfillments/'.$fulfillmentId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('failed', $data['status']);
        $this->assertSame('Package damaged during transit', $data['failureReason']);
        $this->assertNotNull($data['failedAt']);
    }

    public function testMarkAsFailedRequiresReason(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/mark-failed', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCannotModifyDeliveredFulfillment(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        // Complete the fulfillment
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-picking', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
        ]);
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/start-packing', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
        ]);
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/ship', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'carrier' => 'DHL',
            'trackingNumber' => 'DHL123456',
        ]));
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/deliver', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
        ]);

        // Try to cancel delivered fulfillment
        $client->request('PATCH', '/api/fulfillments/'.$fulfillmentId.'/cancel', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testFilterByOrderId(): void
    {
        $client = static::createClient();

        $orderId = OrderId::generate()->toString();
        $fulfillmentId = $this->createTestFulfillment($client, $orderId);

        $client->request('GET', '/api/fulfillments?orderId='.$orderId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame($orderId, $data[0]['orderId']);
    }

    public function testFilterByStatus(): void
    {
        $client = static::createClient();

        $fulfillmentId = $this->createTestFulfillment($client);

        $client->request('GET', '/api/fulfillments?status=assigned', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
        foreach ($data as $fulfillment) {
            $this->assertSame('assigned', $fulfillment['status']);
        }
    }

    public function testRequiresTenantId(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/fulfillments', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function createTestFulfillment($client, ?string $orderId = null): string
    {
        $connection = $client->getContainer()->get('doctrine')->getConnection();

        $fulfillmentId = FulfillmentId::generate()->toString();
        $orderId = $orderId ?? OrderId::generate()->toString();
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
}
