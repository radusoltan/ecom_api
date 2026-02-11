<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Symfony\Component\HttpFoundation\Response;

final class CustomerAddressApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private static int $counter = 0;
    private ?string $customerId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup tenant context for multi-tenancy
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());
        $this->cleanupTestData();

        // Create a customer for address tests
        $this->customerId = $this->createCustomer();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    // ========================================
    // GET /api/customers/{id}/addresses
    // ========================================

    public function testGetAddressesReturnsEmptyArrayForNewCustomer(): void
    {
        $client = $this->createAuthenticatedClient();

        $response = $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testGetAddressesReturnsAddresses(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create two addresses
        $address1Id = $this->createAddress($client, $this->customerId, 'shipping');
        $address2Id = $this->createAddress($client, $this->customerId, 'billing');

        $response = $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        // Verify structure
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('street', $data[0]);
        $this->assertArrayHasKey('city', $data[0]);
        $this->assertArrayHasKey('country', $data[0]);
        $this->assertArrayHasKey('type', $data[0]);
    }

    public function testGetAddressesFilterByType(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create addresses of different types
        $this->createAddress($client, $this->customerId, 'shipping');
        $this->createAddress($client, $this->customerId, 'billing');
        $this->createAddress($client, $this->customerId, 'shipping');

        $response = $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses?type=shipping", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);

        foreach ($data as $address) {
            $this->assertSame('shipping', $address['type']);
        }
    }

    // ========================================
    // GET /api/customers/{id}/addresses/{addressId}
    // ========================================

    public function testGetAddressReturnsAddress(): void
    {
        $client = $this->createAuthenticatedClient();

        $addressId = $this->createAddress($client, $this->customerId);

        $response = $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses/{$addressId}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($response->getContent(), true);
        $this->assertSame($addressId, $data['id']);
        $this->assertSame('123 Main St', $data['street']);
        $this->assertSame('New York', $data['city']);
        $this->assertSame('US', $data['country']);
    }

    public function testGetAddressNotFoundReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $fakeAddressId = '018c4e32-7f3e-7a1a-9f8e-0242ac120099';

        $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses/{$fakeAddressId}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ========================================
    // POST /api/customers/{id}/addresses
    // ========================================

    public function testCreateAddressSuccessfully(): void
    {
        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', "/api/v1/customers/{$this->customerId}/addresses", [
            'json' => [
                'street' => '456 Oak Avenue',
                'street2' => 'Suite 200',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postalCode' => '90001',
                'country' => 'US',
                'type' => 'billing',
                'isDefaultShipping' => false,
                'isDefaultBilling' => true,
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('456 Oak Avenue', $data['street']);
        $this->assertSame('Suite 200', $data['street2']);
        $this->assertSame('Los Angeles', $data['city']);
        $this->assertSame('CA', $data['state']);
        $this->assertSame('90001', $data['postalCode']);
        $this->assertSame('US', $data['country']);
        $this->assertSame('billing', $data['type']);
        $this->assertFalse($data['isDefaultShipping']);
        $this->assertTrue($data['isDefaultBilling']);
    }

    public function testCreateAddressWithValidation(): void
    {
        $client = $this->createAuthenticatedClient();

        // Test minimal required fields
        $response = $client->request('POST', "/api/v1/customers/{$this->customerId}/addresses", [
            'json' => [
                'street' => '789 Elm St',
                'city' => 'Chicago',
                'postalCode' => '60601',
                'country' => 'US',
                'type' => 'shipping',
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('789 Elm St', $data['street']);
        $this->assertNull($data['street2']);
        $this->assertNull($data['state']);
    }

    public function testCreateAddressWithInvalidDataReturns422(): void
    {
        $client = $this->createAuthenticatedClient();

        // Missing required field: street
        $client->request('POST', "/api/v1/customers/{$this->customerId}/addresses", [
            'json' => [
                'city' => 'New York',
                'postalCode' => '10001',
                'country' => 'US',
                'type' => 'shipping',
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateAddressWithoutAuthReturns401(): void
    {
        $client = static::createClient();

        $client->request('POST', "/api/v1/customers/{$this->customerId}/addresses", [
            'json' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'postalCode' => '10001',
                'country' => 'US',
                'type' => 'shipping',
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // ========================================
    // PUT /api/customers/{id}/addresses/{addressId}
    // ========================================

    public function testUpdateAddressSuccessfully(): void
    {
        $client = $this->createAuthenticatedClient();

        $addressId = $this->createAddress($client, $this->customerId);

        $response = $client->request('PUT', "/api/v1/customers/{$this->customerId}/addresses/{$addressId}", [
            'json' => [
                'street' => '999 Updated Street',
                'street2' => 'Floor 5',
                'city' => 'Boston',
                'state' => 'MA',
                'postalCode' => '02101',
                'country' => 'US',
                'type' => 'both',
                'isDefaultShipping' => true,
                'isDefaultBilling' => true,
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($response->getContent(), true);
        $this->assertSame($addressId, $data['id']);
        $this->assertSame('999 Updated Street', $data['street']);
        $this->assertSame('Floor 5', $data['street2']);
        $this->assertSame('Boston', $data['city']);
        $this->assertSame('MA', $data['state']);
        $this->assertSame('both', $data['type']);
    }

    public function testUpdateAddressNotFoundReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $fakeAddressId = '018c4e32-7f3e-7a1a-9f8e-0242ac120099';

        $client->request('PUT', "/api/v1/customers/{$this->customerId}/addresses/{$fakeAddressId}", [
            'json' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'postalCode' => '10001',
                'country' => 'US',
                'type' => 'shipping',
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ========================================
    // DELETE /api/customers/{id}/addresses/{addressId}
    // ========================================

    public function testDeleteAddressSuccessfully(): void
    {
        $client = $this->createAuthenticatedClient();

        $addressId = $this->createAddress($client, $this->customerId);

        $client->request('DELETE', "/api/v1/customers/{$this->customerId}/addresses/{$addressId}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify address was deleted
        $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses/{$addressId}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteAddressNotFoundReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $fakeAddressId = '018c4e32-7f3e-7a1a-9f8e-0242ac120099';

        $client->request('DELETE', "/api/v1/customers/{$this->customerId}/addresses/{$fakeAddressId}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ========================================
    // POST /api/customers/{id}/addresses/{addressId}/set-default-shipping
    // ========================================

    public function testSetDefaultShippingAddress(): void
    {
        $client = $this->createAuthenticatedClient();

        $address1Id = $this->createAddress($client, $this->customerId, 'shipping');
        $address2Id = $this->createAddress($client, $this->customerId, 'shipping');

        $response = $client->request('POST', "/api/v1/customers/{$this->customerId}/addresses/{$address2Id}/set-default-shipping", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Verify address2 is now default
        $response = $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses/{$address2Id}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['isDefaultShipping']);

        // Verify address1 is NOT default
        $response = $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses/{$address1Id}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['isDefaultShipping']);
    }

    // ========================================
    // POST /api/customers/{id}/addresses/{addressId}/set-default-billing
    // ========================================

    public function testSetDefaultBillingAddress(): void
    {
        $client = $this->createAuthenticatedClient();

        $address1Id = $this->createAddress($client, $this->customerId, 'billing');
        $address2Id = $this->createAddress($client, $this->customerId, 'billing');

        $response = $client->request('POST', "/api/v1/customers/{$this->customerId}/addresses/{$address2Id}/set-default-billing", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Verify address2 is now default
        $response = $client->request('GET', "/api/v1/customers/{$this->customerId}/addresses/{$address2Id}", [
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['isDefaultBilling']);
    }

    // ========================================
    // Multi-Tenant Isolation
    // ========================================

    public function testMultiTenantIsolation(): void
    {
        // Create tenant1 with customer and address
        $client = $this->createAuthenticatedClient();

        $customerId1 = $this->createCustomer();
        $addressId1 = $this->createAddress($client, $customerId1);

        // Attempt to access with wrong tenant ID
        $fakeTenantId = '018c4e32-7f3e-7a1a-9f8e-0242ac120099';

        $client->request('GET', "/api/v1/customers/{$customerId1}/addresses/{$addressId1}", [
            'headers' => ['X-Tenant-ID' => $fakeTenantId],
        ]);

        // Should either return 404 or 403 due to RLS
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createAuthenticatedClient(): object
    {
        $client = static::createClient();
        $container = $client->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $email = 'admin@admin.com';
        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername('admin');
            $userEntity->setPassword('$2y$13$dummy.password.hash');
            $userEntity->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_USER']);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($userEntity);
            $entityManager->flush();
        }

        $encoder = $container->get('lexik_jwt_authentication.encoder');

        $token = $encoder->encode([
            'email' => $email,
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        return static::createClient([], ['headers' => ['authorization' => 'Bearer '.$token]]);
    }

    private function generateUniqueEmail(string $prefix = 'customer'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    private function createCustomer(): string
    {
        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/customers', [
            'json' => [
                'email' => $this->generateUniqueEmail(),
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $data = json_decode($response->getContent(), true);

        return $data['id'];
    }

    private function createAddress(object $client, string $customerId, string $type = 'shipping'): string
    {
        $response = $client->request('POST', "/api/v1/customers/{$customerId}/addresses", [
            'json' => [
                'street' => '123 Main St',
                'street2' => null,
                'city' => 'New York',
                'state' => 'NY',
                'postalCode' => '10001',
                'country' => 'US',
                'type' => $type,
                'isDefaultShipping' => false,
                'isDefaultBilling' => false,
            ],
            'headers' => ['X-Tenant-ID' => $this->tenantId->toString()],
        ]);

        $data = json_decode($response->getContent(), true);

        return $data['id'];
    }
}
