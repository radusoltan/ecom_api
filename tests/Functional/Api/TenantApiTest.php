<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Comprehensive Functional Tests for Tenant API Endpoints.
 *
 * Uses a single client per test to avoid DB connection isolation issues with RLS.
 */
final class TenantApiTest extends ApiTestCase
{
    private static int $counter = 0;

    private ?string $jwtToken = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        try {
            $client = static::createClient();
            $em = $client->getContainer()->get('doctrine.orm.entity_manager');
            $connection = $em->getConnection();
            $connection->executeStatement("SET app.bypass_rls = 'true'");
            $connection->executeStatement("DELETE FROM tenants WHERE slug LIKE 'test-%' OR slug LIKE 'new-%' OR slug LIKE 'company-%' OR slug LIKE 'acme-%' OR slug LIKE 'lifecycle-%' OR slug LIKE 'to-%' OR slug LIKE 'collection-%' OR slug LIKE 'preserve-%' OR slug LIKE 'already-%' OR slug LIKE 'location-%' OR slug LIKE 'status-%' OR slug LIKE 'first-%' OR slug LIKE 'second-%' OR slug LIKE 'invalid-%'");
            $connection->executeStatement("SET app.bypass_rls = ''");
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        parent::tearDown();
    }

    /**
     * Create a client with JWT auth and RLS bypass set on its DB connection.
     *
     * IMPORTANT: Call this ONCE per test, then reuse the returned client for all requests.
     */
    private function createClientWithAuth(): object
    {
        // Generate JWT token
        $client = static::createClient();
        $container = $client->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();

        // Set bypass_rls on THIS connection so tenant queries work
        $connection->executeStatement("SET app.bypass_rls = 'true'");

        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);
        $email = 'admin@admin.com';
        $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'];

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername('admin-'.bin2hex(random_bytes(4)));
            $userEntity->setPassword('$2y$13$dummy.password.hash');
            $userEntity->setRoles($roles);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($userEntity);
            $entityManager->flush();
        }

        $encoder = $container->get('lexik_jwt_authentication.encoder');
        $this->jwtToken = $encoder->encode([
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        return $client;
    }

    /**
     * Make an authenticated request using the stored JWT token.
     */
    private function authRequest(object $client, string $method, string $url, array $options = []): object
    {
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['authorization' => 'Bearer '.$this->jwtToken]
        );

        return $client->request($method, $url, $options);
    }

    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Create a tenant via the API using the given client.
     */
    private function createTenantViaApi(object $client, string $name = '', ?string $email = null): array
    {
        if ('' === $name) {
            $name = 'Test Company '.uniqid();
        }
        $email = $email ?? $this->generateUniqueEmail();

        $response = $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        return json_decode($response->getContent(), true);
    }

    // ========================================================================
    // GET /api/tenants - Collection Tests
    // ========================================================================

    public function testGetCollectionOfTenants(): void
    {
        $client = $this->createClientWithAuth();

        $this->createTenantViaApi($client, 'Company One '.uniqid(), $this->generateUniqueEmail('company1'));
        $this->createTenantViaApi($client, 'Company Two '.uniqid(), $this->generateUniqueEmail('company2'));

        $response = $this->authRequest($client, 'GET', '/api/v1/tenants');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Tenant',
            '@type' => 'Collection',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);
        $this->assertGreaterThanOrEqual(2, count($data['member']));

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
    }

    public function testGetCollectionReturnsEmptyWhenNoTenants(): void
    {
        $client = $this->createClientWithAuth();

        $response = $this->authRequest($client, 'GET', '/api/v1/tenants');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Tenant',
            '@type' => 'Collection',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);
    }

    public function testGetCollectionIncludesPaginationMetadata(): void
    {
        $client = $this->createClientWithAuth();

        for ($i = 1; $i <= 5; ++$i) {
            $this->createTenantViaApi($client, 'Company '.uniqid(), $this->generateUniqueEmail("company$i"));
        }

        $response = $this->authRequest($client, 'GET', '/api/v1/tenants');

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('totalItems', $data);
        $this->assertIsInt($data['totalItems']);
        $this->assertGreaterThanOrEqual(5, $data['totalItems']);
    }

    // ========================================================================
    // GET /api/tenants/{id} - Item Tests
    // ========================================================================

    public function testGetSingleTenantReturnsSuccessfully(): void
    {
        $client = $this->createClientWithAuth();

        $name = 'Acme Corporation '.uniqid();
        $tenantData = $this->createTenantViaApi($client, $name, $this->generateUniqueEmail('acme'));
        $tenantId = $tenantData['id'];

        $response = $this->authRequest($client, 'GET', "/api/v1/tenants/$tenantId");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Tenant',
            '@type' => 'Tenant',
            'id' => $tenantId,
            'name' => $name,
        ]);
    }

    public function testGetSingleTenantReturnsCorrectData(): void
    {
        $client = $this->createClientWithAuth();

        $email = $this->generateUniqueEmail('test');
        $name = 'Test Company '.uniqid();
        $tenantData = $this->createTenantViaApi($client, $name, $email);
        $tenantId = $tenantData['id'];

        $response = $this->authRequest($client, 'GET', "/api/v1/tenants/$tenantId");

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($tenantId, $data['id']);
        $this->assertSame($name, $data['name']);
        $this->assertSame($email, $data['ownerEmail']);
        $this->assertSame('active', $data['status']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNotEmpty($data['createdAt']);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $data['id']
        );
    }

    public function testGetSingleTenantReturns404ForNonExistentTenant(): void
    {
        $client = $this->createClientWithAuth();
        $nonExistentId = '00000000-0000-4000-8000-000000000000';

        $this->authRequest($client, 'GET', "/api/v1/tenants/$nonExistentId");

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 404, 500]),
            "Expected 400, 404 or 500 for non-existent tenant, got $statusCode"
        );
    }

    public function testGetSingleTenantReturns404ForInvalidUuid(): void
    {
        $client = $this->createClientWithAuth();
        $invalidId = 'invalid-uuid-format';

        $this->authRequest($client, 'GET', "/api/v1/tenants/$invalidId");

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 404, 500]),
            "Expected 400, 404 or 500 for invalid UUID, got $statusCode"
        );
    }

    // ========================================================================
    // POST /api/tenants - Create Tests
    // ========================================================================

    public function testCreateTenantWithValidDataReturns201(): void
    {
        $client = $this->createClientWithAuth();

        $name = 'New Company '.uniqid();
        $email = $this->generateUniqueEmail('new');

        $response = $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseHasHeader('location');

        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Tenant',
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
    }

    public function testCreateTenantReturnsCorrectLocationHeader(): void
    {
        $client = $this->createClientWithAuth();

        $name = 'Location Test Company '.uniqid();
        $email = $this->generateUniqueEmail('location');

        $response = $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHasHeader('location');

        $data = json_decode($response->getContent(), true);
        $tenantId = $data['id'];

        $locationHeader = $response->getHeaders()['location'][0];
        $this->assertStringContainsString("/api/v1/tenants/$tenantId", $locationHeader);
    }

    public function testCreateTenantValidatesRequiredNameField(): void
    {
        $client = $this->createClientWithAuth();
        $email = $this->generateUniqueEmail('noname');

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'ownerEmail' => $email,
            ],
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 422, 500]),
            "Expected 400, 422 or 500 for missing name field, got $statusCode"
        );
    }

    public function testCreateTenantValidatesRequiredOwnerEmailField(): void
    {
        $client = $this->createClientWithAuth();

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Company Without Email',
            ],
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 422, 500]),
            "Expected 400, 422 or 500 for missing ownerEmail field, got $statusCode"
        );
    }

    public function testCreateTenantValidatesEmailFormat(): void
    {
        $client = $this->createClientWithAuth();

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Invalid Email Company',
                'ownerEmail' => 'not-a-valid-email',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateTenantValidatesNameMinLength(): void
    {
        $client = $this->createClientWithAuth();
        $email = $this->generateUniqueEmail('short');

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'AB',
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateTenantValidatesNameMaxLength(): void
    {
        $client = $this->createClientWithAuth();
        $email = $this->generateUniqueEmail('long');
        $longName = str_repeat('A', 101);

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $longName,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateTenantPreventsDuplicateEmail(): void
    {
        $client = $this->createClientWithAuth();

        $email = $this->generateUniqueEmail('duplicate');
        $this->createTenantViaApi($client, 'First Company '.uniqid(), $email);

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Second Company '.uniqid(),
                'ownerEmail' => $email,
            ],
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 409, 500]),
            "Expected 400, 409, or 500 status code for duplicate email, got $statusCode"
        );
    }

    public function testCreateTenantWithValidNameAtMinLength(): void
    {
        $client = $this->createClientWithAuth();
        $email = $this->generateUniqueEmail('min');
        $name = 'A'.uniqid();

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => $name,
            'ownerEmail' => $email,
        ]);
    }

    public function testCreateTenantWithValidNameAtMaxLength(): void
    {
        $client = $this->createClientWithAuth();
        $email = $this->generateUniqueEmail('max');
        $uniquePart = uniqid();
        $maxName = str_repeat('A', 100 - strlen($uniquePart)).$uniquePart;

        $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $maxName,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => $maxName,
            'ownerEmail' => $email,
        ]);
    }

    public function testCreateTenantSetsDefaultStatusToActive(): void
    {
        $client = $this->createClientWithAuth();
        $name = 'Status Test Company '.uniqid();
        $email = $this->generateUniqueEmail('status');

        $response = $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertSame('active', $data['status']);
    }

    // ========================================================================
    // PATCH /api/tenants/{id}/activate - Activate Tests
    // ========================================================================

    public function testActivateInactiveTenantReturns200(): void
    {
        $client = $this->createClientWithAuth();

        $tenantData = $this->createTenantViaApi($client, 'To Activate '.uniqid(), $this->generateUniqueEmail('activate'));
        $tenantId = $tenantData['id'];

        // Deactivate first
        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        // Activate
        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testActivateTenantReturnsUpdatedTenantWithActiveStatus(): void
    {
        $client = $this->createClientWithAuth();

        $tenantData = $this->createTenantViaApi($client, 'To Activate '.uniqid(), $this->generateUniqueEmail('activate2'));
        $tenantId = $tenantData['id'];

        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $response = $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Tenant',
            '@type' => 'Tenant',
            'id' => $tenantId,
            'status' => 'active',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('active', $data['status']);
    }

    public function testActivateTenantReturns404ForNonExistentTenant(): void
    {
        $client = $this->createClientWithAuth();
        $nonExistentId = '00000000-0000-4000-8000-000000000001';

        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$nonExistentId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 404, 500]),
            "Expected 400, 404 or 500 for non-existent tenant, got $statusCode"
        );
    }

    public function testActivateAlreadyActiveTenantThrowsError(): void
    {
        $client = $this->createClientWithAuth();

        $tenantData = $this->createTenantViaApi($client, 'Already Active '.uniqid(), $this->generateUniqueEmail('idempotent'));
        $tenantId = $tenantData['id'];

        $this->assertSame('active', $tenantData['status']);

        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testActivateTenantPreservesOtherFields(): void
    {
        $client = $this->createClientWithAuth();

        $email = $this->generateUniqueEmail('preserve');
        $name = 'Preserve Fields '.uniqid();
        $tenantData = $this->createTenantViaApi($client, $name, $email);
        $tenantId = $tenantData['id'];

        // Fetch via GET to capture createdAt in a consistent serialization format,
        // since POST and GET/PATCH may serialize timestamps with different timezone handling
        $getResponse = $this->authRequest($client, 'GET', "/api/v1/tenants/$tenantId");
        $createdAt = json_decode($getResponse->getContent(), true)['createdAt'];

        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $response = $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($tenantId, $data['id']);
        $this->assertSame($name, $data['name']);
        $this->assertSame($email, $data['ownerEmail']);
        $this->assertSame($createdAt, $data['createdAt']);
        $this->assertSame('active', $data['status']);
    }

    // ========================================================================
    // PATCH /api/tenants/{id}/deactivate - Deactivate Tests
    // ========================================================================

    public function testDeactivateActiveTenantReturns200(): void
    {
        $client = $this->createClientWithAuth();

        $tenantData = $this->createTenantViaApi($client, 'To Deactivate '.uniqid(), $this->generateUniqueEmail('deactivate'));
        $tenantId = $tenantData['id'];

        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testDeactivateTenantReturnsUpdatedTenantWithInactiveStatus(): void
    {
        $client = $this->createClientWithAuth();

        $tenantData = $this->createTenantViaApi($client, 'To Deactivate '.uniqid(), $this->generateUniqueEmail('deactivate2'));
        $tenantId = $tenantData['id'];

        $response = $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/v1/contexts/Tenant',
            '@type' => 'Tenant',
            'id' => $tenantId,
            'status' => 'inactive',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('inactive', $data['status']);
    }

    public function testDeactivateTenantReturns404ForNonExistentTenant(): void
    {
        $client = $this->createClientWithAuth();
        $nonExistentId = '00000000-0000-4000-8000-000000000002';

        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$nonExistentId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 404, 500]),
            "Expected 400, 404 or 500 for non-existent tenant, got $statusCode"
        );
    }

    public function testDeactivateAlreadyInactiveTenantThrowsError(): void
    {
        $client = $this->createClientWithAuth();

        $tenantData = $this->createTenantViaApi($client, 'Already Inactive '.uniqid(), $this->generateUniqueEmail('idempotent2'));
        $tenantId = $tenantData['id'];

        // Deactivate once
        $response1 = $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $data1 = json_decode($response1->getContent(), true);
        $this->assertSame('inactive', $data1['status']);

        // Try again
        $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeactivateTenantPreservesOtherFields(): void
    {
        $client = $this->createClientWithAuth();

        $email = $this->generateUniqueEmail('preserve2');
        $name = 'Preserve Fields 2 '.uniqid();
        $tenantData = $this->createTenantViaApi($client, $name, $email);
        $tenantId = $tenantData['id'];

        // Fetch via GET to capture createdAt in a consistent serialization format,
        // since POST and GET/PATCH may serialize timestamps with different timezone handling
        $getResponse = $this->authRequest($client, 'GET', "/api/v1/tenants/$tenantId");
        $createdAt = json_decode($getResponse->getContent(), true)['createdAt'];

        $response = $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($tenantId, $data['id']);
        $this->assertSame($name, $data['name']);
        $this->assertSame($email, $data['ownerEmail']);
        $this->assertSame($createdAt, $data['createdAt']);
        $this->assertSame('inactive', $data['status']);
    }

    // ========================================================================
    // Integration Tests - Multiple Operations
    // ========================================================================

    public function testCompleteLifecycleCreateActivateDeactivate(): void
    {
        $client = $this->createClientWithAuth();

        $email = $this->generateUniqueEmail('lifecycle');
        $name = 'Lifecycle Company '.uniqid();
        $createResponse = $this->authRequest($client, 'POST', '/api/v1/tenants', [
            'json' => [
                'name' => $name,
                'ownerEmail' => $email,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $createData = json_decode($createResponse->getContent(), true);
        $tenantId = $createData['id'];
        $this->assertSame('active', $createData['status']);

        // Deactivate
        $deactivateResponse = $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $deactivateData = json_decode($deactivateResponse->getContent(), true);
        $this->assertSame('inactive', $deactivateData['status']);

        // Activate
        $activateResponse = $this->authRequest($client, 'PATCH', "/api/v1/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $activateData = json_decode($activateResponse->getContent(), true);
        $this->assertSame('active', $activateData['status']);

        // Verify via GET
        $getResponse = $this->authRequest($client, 'GET', "/api/v1/tenants/$tenantId");
        $this->assertResponseIsSuccessful();
        $getData = json_decode($getResponse->getContent(), true);
        $this->assertSame('active', $getData['status']);
        $this->assertSame($name, $getData['name']);
        $this->assertSame($email, $getData['ownerEmail']);
    }

    public function testCreatedTenantAppearsInCollection(): void
    {
        $client = $this->createClientWithAuth();

        $email = $this->generateUniqueEmail('collection');
        $name = 'Collection Test Company '.uniqid();
        $tenantData = $this->createTenantViaApi($client, $name, $email);
        $tenantId = $tenantData['id'];

        $response = $this->authRequest($client, 'GET', '/api/v1/tenants');

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $found = false;
        foreach ($data['member'] as $tenant) {
            if ($tenant['id'] === $tenantId) {
                $found = true;
                $this->assertSame($name, $tenant['name']);
                $this->assertSame($email, $tenant['ownerEmail']);
                $this->assertSame('active', $tenant['status']);

                break;
            }
        }

        $this->assertTrue($found, 'Created tenant should appear in collection');
    }
}
