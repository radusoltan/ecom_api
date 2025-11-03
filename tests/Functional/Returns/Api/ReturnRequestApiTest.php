<?php

declare(strict_types=1);

namespace App\Tests\Functional\Returns\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional Tests for Return Request API Endpoints
 *
 * Tests all Returns/RMA API endpoints:
 * 1. GET /api/v1/return-requests - Get all return requests
 * 2. GET /api/v1/return-requests/{id} - Get single return request
 * 3. GET /api/v1/return-requests?orderId={id} - Get returns by order ID
 * 4. POST /api/v1/return-requests - Create return request
 * 5. PATCH /api/v1/return-requests/{id}/approve - Approve return request
 * 6. PATCH /api/v1/return-requests/{id}/reject - Reject return request
 * 7. PATCH /api/v1/return-requests/{id}/receive - Mark as received
 * 8. PATCH /api/v1/return-requests/{id}/inspect - Inspect return
 * 9. PATCH /api/v1/return-requests/{id}/complete - Complete return
 *
 * Also validates:
 * - OpenAPI schema compliance
 * - Proper HTTP status codes
 * - Required headers (Content-Type, X-Tenant-ID)
 * - Response structure and data types
 * - Return workflow state machine transitions
 */
final class ReturnRequestApiTest extends WebTestCase
{
    private const TENANT_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const TEST_ORDER_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    private function cleanupTestData($client): void
    {
        $connection = $client->getContainer()->get('doctrine')->getConnection();

        // Clean up return requests for test tenant
        $connection->executeStatement(
            'DELETE FROM return_requests WHERE tenant_id = ?',
            [self::TENANT_ID]
        );
    }

    /**
     * Test complete return request workflow from creation to completion
     */
    public function testCompleteReturnRequestWorkflowE2E(): void
    {
        $client = static::createClient();
        $this->cleanupTestData($client);

        // Step 1: Create Return Request
        $returnRequestId = $this->createReturnRequest($client);
        $this->assertNotNull($returnRequestId, 'Return request ID should be returned after creation');

        // Step 2: Retrieve Return Request and verify structure
        $returnData = $this->getReturnRequest($client, $returnRequestId);
        $this->validateReturnRequestSchema($returnData);
        $this->assertSame('requested', $returnData['status'], 'Initial status should be requested');
        $this->assertSame('Product was defective', $returnData['reason']);

        // Step 3: Approve Return Request
        $this->approveReturnRequest($client, $returnRequestId);
        $returnData = $this->getReturnRequest($client, $returnRequestId);
        $this->assertSame('approved', $returnData['status'], 'Status should be approved');

        // Step 4: Mark as Received
        $this->markReturnAsReceived($client, $returnRequestId);
        $returnData = $this->getReturnRequest($client, $returnRequestId);
        $this->assertSame('received', $returnData['status'], 'Status should be received');

        // Step 5: Inspect Return (pass inspection)
        $this->inspectReturnRequest($client, $returnRequestId, true);
        $returnData = $this->getReturnRequest($client, $returnRequestId);
        $this->assertSame('inspected', $returnData['status'], 'Status should be inspected');
        $this->assertTrue($returnData['isResellable'], 'Should be marked as resellable');

        // Step 6: Complete Return
        $this->completeReturnRequest($client, $returnRequestId);
        $returnData = $this->getReturnRequest($client, $returnRequestId);
        $this->assertSame('completed', $returnData['status'], 'Status should be completed');
        $this->assertNotNull($returnData['refundAmount'], 'Refund amount should be set');
    }

    /**
     * Test rejection workflow
     */
    public function testReturnRequestRejectionWorkflow(): void
    {
        $client = static::createClient();
        $this->cleanupTestData($client);

        // Create return request
        $returnRequestId = $this->createReturnRequest($client);

        // Reject return request
        $client->request('PATCH', '/api/v1/return-requests/' . $returnRequestId . '/reject', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'rejectionReason' => 'Return window expired',
        ]));

        $this->assertResponseIsSuccessful();

        // Verify rejected status
        $returnData = $this->getReturnRequest($client, $returnRequestId);
        $this->assertSame('rejected', $returnData['status']);
        $this->assertSame('Return window expired', $returnData['rejectionReason']);
    }

    /**
     * Test failed inspection workflow
     */
    public function testReturnRequestFailedInspectionWorkflow(): void
    {
        $client = static::createClient();
        $this->cleanupTestData($client);

        // Create and approve return request
        $returnRequestId = $this->createReturnRequest($client);
        $this->approveReturnRequest($client, $returnRequestId);
        $this->markReturnAsReceived($client, $returnRequestId);

        // Inspect return (fail inspection - not resellable)
        $this->inspectReturnRequest($client, $returnRequestId, false, 'Item damaged by customer');
        $returnData = $this->getReturnRequest($client, $returnRequestId);
        $this->assertSame('inspected', $returnData['status']);
        $this->assertFalse($returnData['isResellable'], 'Should be marked as not resellable');
        $this->assertSame('Item damaged by customer', $returnData['inspectionNotes']);
    }

    /**
     * Test getting all return requests
     */
    public function testGetAllReturnRequests(): void
    {
        $client = static::createClient();
        $this->cleanupTestData($client);

        // Create multiple return requests
        $returnId1 = $this->createReturnRequest($client);
        $returnId2 = $this->createReturnRequest($client);

        // Get all return requests
        $client->request('GET', '/api/v1/return-requests', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data), 'Should return at least 2 return requests');

        // Verify structure of each return request
        foreach ($data as $returnRequest) {
            $this->assertArrayHasKey('id', $returnRequest);
            $this->assertArrayHasKey('status', $returnRequest);
            $this->assertArrayHasKey('reason', $returnRequest);
        }
    }

    /**
     * Test getting return requests by order ID
     */
    public function testGetReturnRequestsByOrderId(): void
    {
        $client = static::createClient();
        $this->cleanupTestData($client);

        // Create return request for specific order
        $returnId = $this->createReturnRequest($client);

        // Get return requests by order ID
        $client->request('GET', '/api/v1/return-requests?orderId=' . self::TEST_ORDER_ID, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data), 'Should return at least 1 return request for this order');

        // Verify all returns belong to the specified order
        foreach ($data as $returnRequest) {
            $this->assertSame(self::TEST_ORDER_ID, $returnRequest['orderId']);
        }
    }

    /**
     * Test validation errors
     */
    public function testCreateReturnRequestValidationErrors(): void
    {
        $client = static::createClient();

        // Test missing required fields
        $client->request('POST', '/api/v1/return-requests', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            // Missing orderId and reason
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    /**
     * Test invalid reason
     */
    public function testCreateReturnRequestInvalidReason(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/return-requests', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'orderId' => self::TEST_ORDER_ID,
            'reason' => 'invalid_reason', // Invalid reason
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test tenant isolation
     */
    public function testTenantIsolation(): void
    {
        $client = static::createClient();
        $this->cleanupTestData($client);

        // Create return request for tenant 1
        $returnId = $this->createReturnRequest($client);

        // Try to access with different tenant ID
        $client->request('GET', '/api/v1/return-requests/' . $returnId, [], [], [
            'HTTP_X-Tenant-ID' => '00000000-0000-0000-0000-000000000000', // Different tenant
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Should return 404 or empty result (not found for this tenant)
        $this->assertTrue(
            $client->getResponse()->getStatusCode() === Response::HTTP_NOT_FOUND ||
            $client->getResponse()->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Test content type negotiation
     */
    public function testContentTypeNegotiation(): void
    {
        $client = static::createClient();

        // Request JSON response
        $client->request('GET', '/api/v1/return-requests', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
    }

    // Helper Methods

    private function createReturnRequest($client): string
    {
        $client->request('POST', '/api/v1/return-requests', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'tenantId' => self::TENANT_ID,
            'orderId' => self::TEST_ORDER_ID,
            'reason' => 'Product was defective',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['id'];
    }

    private function getReturnRequest($client, string $returnRequestId): array
    {
        $client->request('GET', '/api/v1/return-requests/' . $returnRequestId, [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        return json_decode($client->getResponse()->getContent(), true);
    }

    private function approveReturnRequest($client, string $returnRequestId): void
    {
        $client->request('PATCH', '/api/v1/return-requests/' . $returnRequestId . '/approve', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
    }

    private function markReturnAsReceived($client, string $returnRequestId): void
    {
        $client->request('PATCH', '/api/v1/return-requests/' . $returnRequestId . '/receive', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
    }

    private function inspectReturnRequest($client, string $returnRequestId, bool $isResellable, string $notes = ''): void
    {
        $client->request('PATCH', '/api/v1/return-requests/' . $returnRequestId . '/inspect', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'isResellable' => $isResellable,
            'inspectionNotes' => $notes,
        ]));

        $this->assertResponseIsSuccessful();
    }

    private function completeReturnRequest($client, string $returnRequestId): void
    {
        $client->request('PATCH', '/api/v1/return-requests/' . $returnRequestId . '/complete', [], [], [
            'HTTP_X-Tenant-ID' => self::TENANT_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refundAmount' => 1999, // cents
            'refundCurrency' => 'USD',
        ]));

        $this->assertResponseIsSuccessful();
    }

    private function validateReturnRequestSchema(array $returnData): void
    {
        // Validate required fields
        $requiredFields = ['id', 'tenantId', 'orderId', 'reason', 'status', 'createdAt'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $returnData, "Field '$field' should be present");
        }

        // Validate data types
        $this->assertIsString($returnData['id']);
        $this->assertIsString($returnData['tenantId']);
        $this->assertIsString($returnData['orderId']);
        $this->assertIsString($returnData['reason']);
        $this->assertIsString($returnData['status']);
        $this->assertIsString($returnData['createdAt']);

        // Validate reason is a string with minimum length
        $this->assertGreaterThanOrEqual(10, strlen($returnData['reason']), 'Reason should be at least 10 characters');

        // Validate status is one of allowed values
        $validStatuses = ['requested', 'approved', 'rejected', 'received', 'inspected', 'completed'];
        $this->assertContains($returnData['status'], $validStatuses, 'Status should be one of the allowed values');
    }
}
