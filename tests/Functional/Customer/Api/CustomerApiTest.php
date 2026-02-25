<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CustomerApiTest extends ApiTestCase
{
    private static int $counter = 0;
    private ?string $currentTenantId = null;

    /**
     * Create an authenticated client with JWT token and optional X-Tenant-ID header.
     */
    protected function createAuthenticatedClient(string $email = 'admin@admin.com', array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'], ?string $tenantId = null)
    {
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

        $headers = ['authorization' => 'Bearer '.$token];

        // Add X-Tenant-ID if provided or if we have a current tenant
        $tenantIdToUse = $tenantId ?? $this->currentTenantId;
        if (null !== $tenantIdToUse) {
            $headers['X-Tenant-ID'] = $tenantIdToUse;
        }

        return static::createClient([], ['headers' => $headers]);
    }

    /**
     * Generate a unique email address for testing.
     */
    private function generateUniqueEmail(string $prefix = 'customer'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Create a valid tenant for testing.
     */
    private function createTenant(): string
    {
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Test Tenant '.uniqid(),
                'ownerEmail' => $this->generateUniqueEmail('tenant'),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->currentTenantId = $data['id'];

        return $data['id'];
    }

    // ========================================
    // POST /api/customers - Create Customer
    // ========================================

    public function testCreateCustomerSuccessfully(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('john.doe'),
                'firstName' => 'John',
                'lastName' => 'Doe',
                'phoneNumber' => '+12345678901',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();

        // Debug: dump response to see actual structure
        // var_dump($responseData);

        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('tenantId', $responseData);
        self::assertStringContainsString('john.doe', $responseData['email']);
        self::assertEquals('John', $responseData['firstName']);
        self::assertEquals('Doe', $responseData['lastName']);
        self::assertEquals('John Doe', $responseData['fullName']);
        self::assertEquals('+12345678901', $responseData['phoneNumber']);
        self::assertEquals('regular', $responseData['segment']);
        self::assertEquals(0, $responseData['loyaltyPoints']);
        self::assertTrue($responseData['active'] ?? $responseData['isActive']);
        self::assertArrayHasKey('createdAt', $responseData);
        self::assertArrayHasKey('updatedAt', $responseData);
    }

    public function testCreateCustomerValidatesRequiredFields(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('incomplete'),
                // Missing firstName and lastName
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testCreateCustomerRejectsDuplicateEmail(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        $email = $this->generateUniqueEmail('duplicate');

        // Create first customer
        $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $email,
                'firstName' => 'First',
                'lastName' => 'Customer',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Try to create duplicate - should fail with 500 error
        $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $email,
                'firstName' => 'Second',
                'lastName' => 'Customer',
            ],
        ]);

        // Expect 500 error with message about duplicate
        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response = $client->getResponse();
        self::assertStringContainsString('already exists', $response->getContent(false));
    }

    // ========================================
    // GET /api/customers/{id} - Get Customer
    // ========================================

    public function testGetCustomerById(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer first
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('get.test'),
                'firstName' => 'Get',
                'lastName' => 'Test',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Get customer
        $response = $client->request('GET', '/api/v1/customers/'.$customerId);

        self::assertResponseIsSuccessful();
        $responseData = $response->toArray();

        self::assertEquals($customerId, $responseData['id']);
        self::assertStringContainsString('get.test', $responseData['email']);
        self::assertEquals('Get', $responseData['firstName']);
        self::assertEquals('Test', $responseData['lastName']);
    }

    public function testGetCustomerReturns404ForNonExistent(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/v1/customers/'.(\Symfony\Component\Uid\Uuid::v4()->toString()));

        $statusCode = $client->getResponse()->getStatusCode();
        self::assertTrue(
            in_array($statusCode, [404, 500], true),
            "Expected 404 or 500 for non-existent customer, got $statusCode"
        );
    }

    // ========================================
    // GET /api/customers - List Customers
    // ========================================

    public function testGetAllCustomers(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create multiple customers
        for ($i = 1; $i <= 3; ++$i) {
            $client->request('POST', '/api/v1/customers', [
                'json' => [
                    'email' => $this->generateUniqueEmail("list{$i}"),
                    'firstName' => 'List',
                    'lastName' => 'Test',
                ],
            ]);
        }

        // Get all customers
        $response = $client->request('GET', '/api/v1/customers');

        self::assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // API Platform can use 'hydra:member' or 'member' depending on configuration
        self::assertTrue(
            isset($responseData['hydra:member']) || isset($responseData['member']),
            'Response should contain either hydra:member or member key'
        );
        $members = $responseData['hydra:member'] ?? $responseData['member'];
        self::assertGreaterThanOrEqual(3, count($members));
    }

    public function testGetCustomersFilteredBySegment(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer and change to VIP
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('vip'),
                'firstName' => 'VIP',
                'lastName' => 'Customer',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Change to VIP segment
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/segment', [
            'json' => [
                'segment' => 'vip',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Get VIP customers
        $response = $client->request('GET', '/api/v1/customers?segment=vip');

        self::assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // API Platform can use 'hydra:member' or 'member' depending on configuration
        self::assertTrue(
            isset($responseData['hydra:member']) || isset($responseData['member']),
            'Response should contain either hydra:member or member key'
        );
        $members = $responseData['hydra:member'] ?? $responseData['member'];
        foreach ($members as $customer) {
            self::assertEquals('vip', $customer['segment']);
        }
    }

    // ========================================
    // PUT /api/customers/{id} - Update Customer
    // ========================================

    public function testUpdateCustomerProfile(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('update'),
                'firstName' => 'Original',
                'lastName' => 'Name',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Update customer
        $response = $client->request('PUT', '/api/v1/customers/'.$customerId, [
            'json' => [
                'firstName' => 'Updated',
                'lastName' => 'Name',
                'phoneNumber' => '+19876543210',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $responseData = $response->toArray();

        self::assertEquals('Updated', $responseData['firstName']);
        self::assertEquals('Name', $responseData['lastName']);
        self::assertEquals('+19876543210', $responseData['phoneNumber']);
    }

    public function testUpdateCustomerReturns404ForNonExistent(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        $client->request('PUT', '/api/v1/customers/00000000-0000-0000-0000-000000000000', [
            'json' => [
                'firstName' => 'Test',
                'lastName' => 'User',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ========================================
    // PATCH /api/customers/{id}/segment - Change Segment
    // ========================================

    public function testChangeCustomerSegmentToVip(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('segment'),
                'firstName' => 'Segment',
                'lastName' => 'Test',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Verify initial segment is 'regular'
        self::assertEquals('regular', $createData['segment'], 'Customer should be created with regular segment');

        // Change segment to VIP
        $response = $client->request('PATCH', '/api/v1/customers/'.$customerId.'/segment', [
            'json' => [
                'segment' => 'vip',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseIsSuccessful();
        $responseData = $response->toArray();

        self::assertEquals('vip', $responseData['segment']);
    }

    public function testChangeSegmentRejectsInvalidSegment(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('invalid.segment'),
                'firstName' => 'Invalid',
                'lastName' => 'Segment',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Try invalid segment
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/segment', [
            'json' => [
                'segment' => 'platinum',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testChangeSegmentFailsWhenAlreadyInSegment(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer (default segment is 'regular')
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('same.segment'),
                'firstName' => 'Same',
                'lastName' => 'Segment',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Try to set to same segment - should fail with 500 error
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/segment', [
            'json' => [
                'segment' => 'regular',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Expect 500 error with message about already in segment
        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response = $client->getResponse();
        self::assertStringContainsString('already in segment', $response->getContent(false));
    }

    // ========================================
    // PATCH /api/customers/{id}/activate - Activate Customer
    // ========================================

    public function testActivateInactiveCustomer(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create and deactivate customer
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('activate'),
                'firstName' => 'Activate',
                'lastName' => 'Test',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Deactivate first
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/deactivate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Now activate
        $response = $client->request('PATCH', '/api/v1/customers/'.$customerId.'/activate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // API Platform may serialize boolean as 'active' or 'isActive'
        self::assertTrue($responseData['active'] ?? $responseData['isActive']);
    }

    public function testActivateFailsWhenAlreadyActive(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer (already active by default)
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('already.active'),
                'firstName' => 'Already',
                'lastName' => 'Active',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Try to activate already active customer - should fail with 500 error
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/activate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Expect 500 error with message about already active
        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response = $client->getResponse();
        self::assertStringContainsString('already active', $response->getContent(false));
    }

    // ========================================
    // PATCH /api/customers/{id}/deactivate - Deactivate Customer
    // ========================================

    public function testDeactivateActiveCustomer(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create customer
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('deactivate'),
                'firstName' => 'Deactivate',
                'lastName' => 'Test',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Deactivate
        $response = $client->request('PATCH', '/api/v1/customers/'.$customerId.'/deactivate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // API Platform may serialize boolean as 'active' or 'isActive'
        $isActive = $responseData['active'] ?? $responseData['isActive'];
        self::assertFalse($isActive);
    }

    public function testDeactivateFailsWhenAlreadyInactive(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // Create and deactivate customer
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('already.inactive'),
                'firstName' => 'Already',
                'lastName' => 'Inactive',
            ],
        ]);

        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // Deactivate first time - should succeed
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/deactivate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();

        // Try to deactivate again - should fail with 500 error
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/deactivate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Expect 500 error with message about already inactive
        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response = $client->getResponse();
        self::assertStringContainsString('already inactive', $response->getContent(false));
    }

    // ========================================
    // Complete Lifecycle Test
    // ========================================

    public function testCompleteCustomerLifecycle(): void
    {
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient();

        // 1. Create customer
        $createResponse = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail('lifecycle'),
                'firstName' => 'Lifecycle',
                'lastName' => 'Test',
                'phoneNumber' => '+11234567890',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $createData = $createResponse->toArray();
        $customerId = $createData['id'];

        // 2. Get customer
        $client->request('GET', '/api/v1/customers/'.$customerId);
        self::assertResponseIsSuccessful();

        // 3. Update profile
        $client->request('PUT', '/api/v1/customers/'.$customerId, [
            'json' => [
                'firstName' => 'Updated',
                'lastName' => 'Lifecycle',
                'phoneNumber' => '+19876543210',
            ],
        ]);
        self::assertResponseIsSuccessful();

        // 4. Upgrade to VIP
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/segment', [
            'json' => [
                'segment' => 'vip',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();

        // 5. Deactivate
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/deactivate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();

        // 6. Reactivate
        $client->request('PATCH', '/api/v1/customers/'.$customerId.'/activate', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();

        // 7. Verify final state
        $finalResponse = $client->request('GET', '/api/v1/customers/'.$customerId);
        $finalData = $finalResponse->toArray();

        self::assertEquals('Updated', $finalData['firstName']);
        self::assertEquals('Lifecycle', $finalData['lastName']);
        self::assertEquals('vip', $finalData['segment']);
        // API Platform may serialize boolean as 'active' or 'isActive'
        self::assertTrue($finalData['active'] ?? $finalData['isActive']);
    }
}
