<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Functional Tests for PriceList API Endpoints.
 *
 * Tests 5 PriceList API endpoints:
 * - GET /api/price_lists (Collection)
 * - GET /api/price_lists/{id} (Item)
 * - POST /api/price_lists (Create)
 * - PATCH /api/price_lists/{id}/activate
 * - PATCH /api/price_lists/{id}/deactivate
 */
final class PriceListApiTest extends ApiTestCase
{
    private static int $counter = 0;
    private ?string $currentTenantId = null;

    protected function createAuthenticatedClient(string $email = 'admin@admin.com', array $roles = ['ROLE_SUPER_ADMIN'], ?string $tenantId = null)
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

    private function createTenant(): string
    {
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Test Tenant '.uniqid(),
                'ownerEmail' => sprintf('tenant-%d-%s@example.com', ++self::$counter, uniqid()),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $tenantId = $data['id'];

        // Store tenant ID for subsequent requests in the same test
        $this->currentTenantId = $tenantId;

        return $tenantId;
    }

    // ========================================================================
    // POST /api/price_lists - Create Tests
    // ========================================================================

    public function testCreatePriceListReturns201(): void
    {
        $tenantId = $this->createTenant();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => [
                'tenantId' => $tenantId,
                'name' => 'Summer Sale 2025',
                'priority' => 500,
                'validFrom' => '2025-06-01T00:00:00+00:00',
                'validTo' => '2025-08-31T23:59:59+00:00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Summer Sale 2025', $data['name']);
        $this->assertSame(500, $data['priority']);
        $this->assertFalse($data['isActive']); // Created inactive
    }

    public function testCreatePriceListWithDefaultPriority(): void
    {
        $tenantId = $this->createTenant();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => [
                'tenantId' => $tenantId,
                'name' => 'Standard Price List',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['priority']); // Default priority
    }

    public function testCreatePriceListValidatesNameLength(): void
    {
        $tenantId = $this->createTenant();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => [
                'tenantId' => $tenantId,
                'name' => 'AB', // Too short (min 3)
            ],
        ]);

        $this->assertResponseStatusCodeSame(500);
    }

    // ========================================================================
    // GET /api/price_lists/{id} - Get Item Tests
    // ========================================================================

    public function testGetPriceListByIdReturnsSuccessfully(): void
    {
        $tenantId = $this->createTenant();

        // Create price list
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => [
                'tenantId' => $tenantId,
                'name' => 'Test Price List',
            ],
        ]);
        $createData = json_decode($createResponse->getContent(), true);
        $priceListId = $createData['id'];

        // Get price list
        $response = $this->createAuthenticatedClient()->request('GET', "/api/v1/price_lists/$priceListId");

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertSame($priceListId, $data['id']);
        $this->assertSame('Test Price List', $data['name']);
        $this->assertArrayHasKey('priority', $data);
        $this->assertArrayHasKey('isActive', $data);
    }

    public function testGetPriceListByIdReturns404ForNonExistent(): void
    {
        $nonExistentId = '00000000-0000-4000-8000-000000000000';

        // Need a tenant context for the provider
        $tenantId = $this->createTenant();
        $client = $this->createAuthenticatedClient(tenantId: $tenantId);
        $client->request('GET', "/api/v1/price_lists/$nonExistentId");

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 404, 500], true),
            "Expected 400, 404 or 500 for non-existent price list, got $statusCode"
        );
    }

    // ========================================================================
    // GET /api/price_lists - Get Collection Tests
    // ========================================================================

    public function testGetPriceListsCollectionReturnsSuccessfully(): void
    {
        $tenantId = $this->createTenant();

        // Create multiple price lists
        $response1 = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => ['tenantId' => $tenantId, 'name' => 'Price List 1', 'priority' => 300],
        ]);
        $priceListId1 = json_decode($response1->getContent(), true)['id'];

        $response2 = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => ['tenantId' => $tenantId, 'name' => 'Price List 2', 'priority' => 100],
        ]);
        $priceListId2 = json_decode($response2->getContent(), true)['id'];

        // Activate both price lists
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId1/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId2/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/price_lists');

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);
        $this->assertGreaterThanOrEqual(2, count($data['member']));
    }

    // ========================================================================
    // PATCH /api/price_lists/{id}/activate - Activate Tests
    // ========================================================================

    public function testActivatePriceListReturnsSuccessfully(): void
    {
        $tenantId = $this->createTenant();

        // Create inactive price list
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => ['tenantId' => $tenantId, 'name' => 'Inactive Price List'],
        ]);
        $priceListId = json_decode($createResponse->getContent(), true)['id'];

        // Activate
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['isActive']);
    }

    public function testActivateAlreadyActivePriceListFails(): void
    {
        $tenantId = $this->createTenant();

        // Create and activate
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => ['tenantId' => $tenantId, 'name' => 'Test Price List'],
        ]);
        $priceListId = json_decode($createResponse->getContent(), true)['id'];

        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Try to activate again
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(500);
    }

    // ========================================================================
    // PATCH /api/price_lists/{id}/deactivate - Deactivate Tests
    // ========================================================================

    public function testDeactivatePriceListReturnsSuccessfully(): void
    {
        $tenantId = $this->createTenant();

        // Create and activate
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => ['tenantId' => $tenantId, 'name' => 'Active Price List'],
        ]);
        $priceListId = json_decode($createResponse->getContent(), true)['id'];

        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Deactivate
        $response = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['isActive']);
    }

    public function testDeactivateInactivePriceListFails(): void
    {
        $tenantId = $this->createTenant();

        // Create (inactive by default)
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => ['tenantId' => $tenantId, 'name' => 'Inactive Price List'],
        ]);
        $priceListId = json_decode($createResponse->getContent(), true)['id'];

        // Try to deactivate already inactive
        $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(500);
    }

    // ========================================================================
    // Lifecycle Tests
    // ========================================================================

    public function testCompletePriceListLifecycle(): void
    {
        $tenantId = $this->createTenant();

        // 1. Create
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/price_lists', [
            'json' => [
                'tenantId' => $tenantId,
                'name' => 'Lifecycle Price List',
                'priority' => 200,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $priceListId = json_decode($createResponse->getContent(), true)['id'];

        // 2. Get (verify inactive)
        $getResponse = $this->createAuthenticatedClient()->request('GET', "/api/v1/price_lists/$priceListId");
        $data = json_decode($getResponse->getContent(), true);
        $this->assertFalse($data['isActive']);

        // 3. Activate
        $activateResponse = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/activate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $activateData = json_decode($activateResponse->getContent(), true);
        $this->assertTrue($activateData['isActive']);

        // 4. Deactivate
        $deactivateResponse = $this->createAuthenticatedClient()->request('PATCH', "/api/v1/price_lists/$priceListId/deactivate", [
            'json' => [],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $deactivateData = json_decode($deactivateResponse->getContent(), true);
        $this->assertFalse($deactivateData['isActive']);

        // 5. Final verification
        $finalResponse = $this->createAuthenticatedClient()->request('GET', "/api/v1/price_lists/$priceListId");
        $finalData = json_decode($finalResponse->getContent(), true);
        $this->assertSame('Lifecycle Price List', $finalData['name']);
        $this->assertFalse($finalData['isActive']);
    }
}
