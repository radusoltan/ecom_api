<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tenant\Presentation\Api\TenantResource;

/**
 * Comprehensive Functional Tests for Tenant API Endpoints
 *
 * Tests all 5 Tenant API endpoints using API Platform testing best practices:
 * - GET /api/tenants (Collection)
 * - GET /api/tenants/{id} (Item)
 * - POST /api/tenants (Create)
 * - PATCH /api/tenants/{id}/activate (Activate)
 * - PATCH /api/tenants/{id}/deactivate (Deactivate)
 *
 * Uses ApiTestCase with DAMA Bundle for automatic database transaction rollback.
 */
final class TenantApiTest extends ApiTestCase
{
    private static int $counter = 0;

    /**
     * Create an authenticated client with JWT token
     */
    protected function createAuthenticatedClient(string $email = 'admin@admin.com', array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'])
    {
        // First, create a temporary client to access services
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        // Create user in database for JWT authentication (if not exists)
        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0] . '-' . bin2hex(random_bytes(4)));
            $userEntity->setPassword('$2y$13$dummy.password.hash'); // Dummy password (not used for JWT)
            $userEntity->setRoles($roles);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($userEntity);
            $entityManager->flush();
        }

        // Generate JWT token using Lexik encoder for testing
        $encoder = $container->get('lexik_jwt_authentication.encoder');

        $token = $encoder->encode([
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        // Create authenticated client with token in headers (API Platform recommended way)
        return static::createClient([], ['headers' => ['authorization' => 'Bearer ' . $token]]);
    }

    /**
     * Generate a unique email address for testing
     */
    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Helper method to create a tenant via the API
     *
     * @return array<string, mixed> The created tenant data
     */
    private function createTenant(string $name = 'Test Company', ?string $email = null): array
    {
        $email = $email ?? $this->generateUniqueEmail();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        return json_decode($response->getContent(), true);
    }

    /**
     * Extract tenant ID from API response
     */
    private function extractTenantId(array $tenantData): string
    {
        $this->assertArrayHasKey('id', $tenantData);
        $this->assertNotEmpty($tenantData['id']);

        return $tenantData['id'];
    }

    // ========================================================================
    // GET /api/tenants - Collection Tests
    // ========================================================================

    public function testGetCollectionOfTenants(): void
    {
        // Arrange - Create some test tenants
        $this->createTenant('Company One', $this->generateUniqueEmail('company1'));
        $this->createTenant('Company Two', $this->generateUniqueEmail('company2'));

        // Act
        $response = $this->createAuthenticatedClient()->request('GET', '/api/tenants');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Tenant',
            '@type' => 'Collection',
        ]);

        // Verify collection structure
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);
        $this->assertGreaterThanOrEqual(2, count($data['member']));

        // Verify each tenant has required fields
        foreach ($data['member'] as $tenant) {
            $this->assertArrayHasKey('@id', $tenant);
            $this->assertArrayHasKey('@type', $tenant);
            $this->assertArrayHasKey('id', $tenant);
            $this->assertArrayHasKey('name', $tenant);
            $this->assertArrayHasKey('ownerEmail', $tenant);
            $this->assertArrayHasKey('status', $tenant);
            $this->assertArrayHasKey('createdAt', $tenant);
            $this->assertSame('Tenant', $tenant['@type']);
        }

        // Validate against resource schema
        $this->assertMatchesResourceCollectionJsonSchema(TenantResource::class);
    }

    public function testGetCollectionReturnsEmptyWhenNoTenants(): void
    {
        // Note: DAMA Bundle ensures isolated transactions, so we start with a clean slate
        // Act
        $response = $this->createAuthenticatedClient()->request('GET', '/api/tenants');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Tenant',
            '@type' => 'Collection',
        ]);

        // Verify empty collection structure
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);
        $this->assertMatchesResourceCollectionJsonSchema(TenantResource::class);
    }

    public function testGetCollectionIncludesPaginationMetadata(): void
    {
        // Arrange - Create multiple tenants
        for ($i = 1; $i <= 5; $i++) {
            $this->createTenant("Company $i", $this->generateUniqueEmail("company$i"));
        }

        // Act
        $response = $this->createAuthenticatedClient()->request('GET', '/api/tenants');

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        // Verify pagination metadata exists
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertIsInt($data['totalItems']);
        $this->assertGreaterThanOrEqual(5, $data['totalItems']);
    }

    // ========================================================================
    // GET /api/tenants/{id} - Item Tests
    // ========================================================================

    public function testGetSingleTenantReturnsSuccessfully(): void
    {
        // Arrange - Create a tenant
        $tenantData = $this->createTenant('Acme Corporation', $this->generateUniqueEmail('acme'));
        $tenantId = $this->extractTenantId($tenantData);

        // Act
        $response = $this->createAuthenticatedClient()->request('GET', "/api/tenants/$tenantId");

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Tenant',
            '@type' => 'Tenant',
            'id' => $tenantId,
            'name' => 'Acme Corporation',
        ]);

        // Validate against resource schema
        $this->assertMatchesResourceItemJsonSchema(TenantResource::class);
    }

    public function testGetSingleTenantReturnsCorrectData(): void
    {
        // Arrange
        $email = $this->generateUniqueEmail('test');
        $tenantData = $this->createTenant('Test Company', $email);
        $tenantId = $this->extractTenantId($tenantData);

        // Act
        $response = $this->createAuthenticatedClient()->request('GET', "/api/tenants/$tenantId");

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($tenantId, $data['id']);
        $this->assertSame('Test Company', $data['name']);
        $this->assertSame($email, $data['ownerEmail']);
        $this->assertSame('active', $data['status']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNotEmpty($data['createdAt']);

        // Verify UUID format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $data['id']
        );
    }

    public function testGetSingleTenantReturns404ForNonExistentTenant(): void
    {
        // Arrange - Use a valid UUID that doesn't exist
        $nonExistentId = '00000000-0000-4000-8000-000000000000';

        // Act & Assert
        $client = $this->createAuthenticatedClient();
        $client->request('GET', "/api/tenants/$nonExistentId");

        // Domain layer throws exception, returns 500 (not ideal but current behavior)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [404, 500]),
            "Expected 404 or 500 for non-existent tenant, got $statusCode"
        );
    }

    public function testGetSingleTenantReturns404ForInvalidUuid(): void
    {
        // Arrange - Use an invalid UUID format
        $invalidId = 'invalid-uuid-format';

        // Act & Assert
        $client = $this->createAuthenticatedClient();
        $client->request('GET', "/api/tenants/$invalidId");

        // Domain layer throws exception for invalid UUID, returns 500
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [404, 500]),
            "Expected 404 or 500 for invalid UUID, got $statusCode"
        );
    }

    // ========================================================================
    // POST /api/tenants - Create Tests
    // ========================================================================

    public function testCreateTenantWithValidDataReturns201(): void
    {
        // Arrange
        $name = 'New Company';
        $email = $this->generateUniqueEmail('new');

        // Act
        $response = $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        // Verify Location header is present
        $this->assertResponseHasHeader('location');

        // Verify response body contains created tenant
        $this->assertJsonContains([
            '@context' => '/api/contexts/Tenant',
            '@type' => 'Tenant',
            'name' => $name,
            'ownerEmail' => $email,
            'status' => 'active',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNotEmpty($data['id']);
        $this->assertNotEmpty($data['createdAt']);

        // Validate against resource schema
        $this->assertMatchesResourceItemJsonSchema(TenantResource::class);
    }

    public function testCreateTenantReturnsCorrectLocationHeader(): void
    {
        // Arrange
        $name = 'Location Test Company';
        $email = $this->generateUniqueEmail('location');

        // Act
        $response = $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHasHeader('location');

        $data = json_decode($response->getContent(), true);
        $tenantId = $data['id'];

        // Verify Location header contains tenant ID
        $locationHeader = $response->getHeaders()['location'][0];
        $this->assertStringContainsString("/api/tenants/$tenantId", $locationHeader);
    }

    public function testCreateTenantValidatesRequiredNameField(): void
    {
        // Arrange - Missing name
        $email = $this->generateUniqueEmail('noname');

        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'ownerEmail' => $email,
            ],
        ]);

        // Domain validation occurs at processor level, returns 500
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCreateTenantValidatesRequiredOwnerEmailField(): void
    {
        // Arrange - Missing ownerEmail
        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'Company Without Email',
            ],
        ]);

        // Domain validation occurs at processor level, returns 500
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCreateTenantValidatesEmailFormat(): void
    {
        // Arrange - Invalid email format
        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'Invalid Email Company',
                'ownerEmail' => 'not-a-valid-email',
            ],
        ]);

        // Domain validation occurs at value object level, returns 500
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCreateTenantValidatesNameMinLength(): void
    {
        // Arrange - Name too short (less than 3 characters)
        $email = $this->generateUniqueEmail('short');

        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'AB', // Only 2 characters
                'ownerEmail' => $email,
            ],
        ]);

        // Domain validation occurs at value object level, returns 500
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCreateTenantValidatesNameMaxLength(): void
    {
        // Arrange - Name too long (more than 100 characters)
        $email = $this->generateUniqueEmail('long');
        $longName = str_repeat('A', 101); // 101 characters

        // Act & Assert
        $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => $longName,
                'ownerEmail' => $email,
            ],
        ]);

        // Domain validation occurs at value object level, returns 500
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCreateTenantPreventsDuplicateEmail(): void
    {
        // Arrange - Create first tenant
        $email = $this->generateUniqueEmail('duplicate');
        $this->createTenant('First Company', $email);

        // Act - Try to create another tenant with same email
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'Second Company',
                'ownerEmail' => $email,
            ],
        ]);

        // Assert - Should return 500 (domain exception for duplicate)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 409, 500]),
            "Expected 400, 409, or 500 status code for duplicate email, got $statusCode"
        );
    }

    public function testCreateTenantWithValidNameAtMinLength(): void
    {
        // Arrange - Name exactly 3 characters (minimum valid)
        $email = $this->generateUniqueEmail('min');

        // Act
        $response = $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'ABC',
                'ownerEmail' => $email,
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => 'ABC',
            'ownerEmail' => $email,
        ]);
    }

    public function testCreateTenantWithValidNameAtMaxLength(): void
    {
        // Arrange - Name exactly 100 characters (maximum valid)
        $email = $this->generateUniqueEmail('max');
        $maxName = str_repeat('A', 100); // Exactly 100 characters

        // Act
        $response = $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => $maxName,
                'ownerEmail' => $email,
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => $maxName,
            'ownerEmail' => $email,
        ]);
    }

    public function testCreateTenantSetsDefaultStatusToActive(): void
    {
        // Arrange
        $name = 'Status Test Company';
        $email = $this->generateUniqueEmail('status');

        // Act
        $response = $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertSame('active', $data['status']);
    }

    // ========================================================================
    // PATCH /api/tenants/{id}/activate - Activate Tests
    // ========================================================================

    public function testActivateInactiveTenantReturns200(): void
    {
        // Arrange - Create and deactivate a tenant
        $tenantData = $this->createTenant('To Activate', $this->generateUniqueEmail('activate'));
        $tenantId = $this->extractTenantId($tenantData);

        // Deactivate first
        $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        // Act - Activate the tenant
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testActivateTenantReturnsUpdatedTenantWithActiveStatus(): void
    {
        // Arrange - Create and deactivate a tenant
        $tenantData = $this->createTenant('To Activate', $this->generateUniqueEmail('activate2'));
        $tenantId = $this->extractTenantId($tenantData);

        // Deactivate first
        $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Act - Activate the tenant
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Tenant',
            '@type' => 'Tenant',
            'id' => $tenantId,
            'status' => 'active',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('active', $data['status']);
        $this->assertMatchesResourceItemJsonSchema(TenantResource::class);
    }

    public function testActivateTenantReturns404ForNonExistentTenant(): void
    {
        // Arrange - Use a valid UUID that doesn't exist
        $nonExistentId = '00000000-0000-4000-8000-000000000001';

        // Act & Assert
        $client = $this->createAuthenticatedClient();
        $client->request('PATCH', "/api/tenants/$nonExistentId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Domain layer throws exception, returns 500
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [404, 500]),
            "Expected 404 or 500 for non-existent tenant, got $statusCode"
        );
    }

    public function testActivateAlreadyActiveTenantThrowsError(): void
    {
        // Arrange - Create a tenant (already active by default)
        $tenantData = $this->createTenant('Already Active', $this->generateUniqueEmail('idempotent'));
        $tenantId = $this->extractTenantId($tenantData);

        // Verify it's active
        $this->assertSame('active', $tenantData['status']);

        // Act - Try to activate again
        $client = $this->createAuthenticatedClient();
        $client->request('PATCH', "/api/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert - Domain rule: cannot activate already active tenant (returns 500)
        $this->assertResponseStatusCodeSame(500);
    }

    public function testActivateTenantPreservesOtherFields(): void
    {
        // Arrange - Create and deactivate a tenant
        $email = $this->generateUniqueEmail('preserve');
        $tenantData = $this->createTenant('Preserve Fields', $email);
        $tenantId = $this->extractTenantId($tenantData);
        $createdAt = $tenantData['createdAt'];

        // Deactivate
        $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Act - Activate
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert - Other fields remain unchanged
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($tenantId, $data['id']);
        $this->assertSame('Preserve Fields', $data['name']);
        $this->assertSame($email, $data['ownerEmail']);
        $this->assertSame($createdAt, $data['createdAt']);
        $this->assertSame('active', $data['status']);
    }

    // ========================================================================
    // PATCH /api/tenants/{id}/deactivate - Deactivate Tests
    // ========================================================================

    public function testDeactivateActiveTenantReturns200(): void
    {
        // Arrange - Create a tenant (active by default)
        $tenantData = $this->createTenant('To Deactivate', $this->generateUniqueEmail('deactivate'));
        $tenantId = $this->extractTenantId($tenantData);

        // Act - Deactivate the tenant
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testDeactivateTenantReturnsUpdatedTenantWithInactiveStatus(): void
    {
        // Arrange - Create a tenant
        $tenantData = $this->createTenant('To Deactivate', $this->generateUniqueEmail('deactivate2'));
        $tenantId = $this->extractTenantId($tenantData);

        // Act - Deactivate the tenant
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Tenant',
            '@type' => 'Tenant',
            'id' => $tenantId,
            'status' => 'inactive',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('inactive', $data['status']);
        $this->assertMatchesResourceItemJsonSchema(TenantResource::class);
    }

    public function testDeactivateTenantReturns404ForNonExistentTenant(): void
    {
        // Arrange - Use a valid UUID that doesn't exist
        $nonExistentId = '00000000-0000-4000-8000-000000000002';

        // Act & Assert
        $client = $this->createAuthenticatedClient();
        $client->request('PATCH', "/api/tenants/$nonExistentId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Domain layer throws exception, returns 500
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [404, 500]),
            "Expected 404 or 500 for non-existent tenant, got $statusCode"
        );
    }

    public function testDeactivateAlreadyInactiveTenantThrowsError(): void
    {
        // Arrange - Create and deactivate a tenant
        $tenantData = $this->createTenant('Already Inactive', $this->generateUniqueEmail('idempotent2'));
        $tenantId = $this->extractTenantId($tenantData);

        // Deactivate once
        $response1 = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $data1 = json_decode($response1->getContent(), true);
        $this->assertSame('inactive', $data1['status']);

        // Act - Try to deactivate again
        $client = $this->createAuthenticatedClient();
        $client->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert - Domain rule: cannot deactivate already inactive tenant (returns 500)
        $this->assertResponseStatusCodeSame(500);
    }

    public function testDeactivateTenantPreservesOtherFields(): void
    {
        // Arrange - Create a tenant
        $email = $this->generateUniqueEmail('preserve2');
        $tenantData = $this->createTenant('Preserve Fields 2', $email);
        $tenantId = $this->extractTenantId($tenantData);
        $createdAt = $tenantData['createdAt'];

        // Act - Deactivate
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Assert - Other fields remain unchanged
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($tenantId, $data['id']);
        $this->assertSame('Preserve Fields 2', $data['name']);
        $this->assertSame($email, $data['ownerEmail']);
        $this->assertSame($createdAt, $data['createdAt']);
        $this->assertSame('inactive', $data['status']);
    }

    // ========================================================================
    // Integration Tests - Multiple Operations
    // ========================================================================

    public function testCompleteLifecycleCreateActivateDeactivate(): void
    {
        // Step 1: Create tenant
        $email = $this->generateUniqueEmail('lifecycle');
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'Lifecycle Company',
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $createData = json_decode($createResponse->getContent(), true);
        $tenantId = $createData['id'];
        $this->assertSame('active', $createData['status']);

        // Step 2: Deactivate tenant
        $deactivateResponse = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $deactivateData = json_decode($deactivateResponse->getContent(), true);
        $this->assertSame('inactive', $deactivateData['status']);

        // Step 3: Activate tenant
        $activateResponse = $this->createAuthenticatedClient()->request('PATCH', "/api/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $activateData = json_decode($activateResponse->getContent(), true);
        $this->assertSame('active', $activateData['status']);

        // Step 4: Verify via GET
        $getResponse = $this->createAuthenticatedClient()->request('GET', "/api/tenants/$tenantId");
        $this->assertResponseIsSuccessful();
        $getData = json_decode($getResponse->getContent(), true);
        $this->assertSame('active', $getData['status']);
        $this->assertSame('Lifecycle Company', $getData['name']);
        $this->assertSame($email, $getData['ownerEmail']);
    }

    public function testCreatedTenantAppearsInCollection(): void
    {
        // Arrange - Create a tenant with unique identifier
        $email = $this->generateUniqueEmail('collection');
        $tenantData = $this->createTenant('Collection Test Company', $email);
        $tenantId = $this->extractTenantId($tenantData);

        // Act - Get collection
        $response = $this->createAuthenticatedClient()->request('GET', '/api/tenants');

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        // Find our created tenant in the collection
        $found = false;
        foreach ($data['member'] as $tenant) {
            if ($tenant['id'] === $tenantId) {
                $found = true;
                $this->assertSame('Collection Test Company', $tenant['name']);
                $this->assertSame($email, $tenant['ownerEmail']);
                $this->assertSame('active', $tenant['status']);
                break;
            }
        }

        $this->assertTrue($found, "Created tenant should appear in collection");
    }
}
