<?php

declare(strict_types=1);

namespace App\Tests\Functional\Tax\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Comprehensive Functional Tests for Tax Rule API Endpoints
 *
 * Tests all Tax Rule API endpoints:
 * - GET /api/tax_rules (Collection with pagination)
 * - GET /api/tax_rules/{id} (Item)
 * - POST /api/tax_rules (Create Tax Rule)
 * - PATCH /api/tax_rules/{id} (Update Tax Rule)
 * - PATCH /api/tax_rules/{id}/deactivate (Deactivate Tax Rule)
 *
 * Uses ApiTestCase with DAMA Bundle for automatic database transaction rollback.
 *
 * @group functional
 * @group tax
 * @group api
 */
final class TaxRuleApiTest extends ApiTestCase
{
    private static int $counter = 0;
    private ?string $currentTenantId = null;

    /**
     * Create an authenticated client with JWT token and optional X-Tenant-ID header
     */
    protected function createAuthenticatedClient(
        string $email = 'admin@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
        ?string $tenantId = null
    ) {
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0] . '-' . bin2hex(random_bytes(4)));
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

        $headers = ['authorization' => 'Bearer ' . $token];

        // Add X-Tenant-ID if provided or if we have a current tenant
        $tenantIdToUse = $tenantId ?? $this->currentTenantId;
        if ($tenantIdToUse !== null) {
            $headers['X-Tenant-ID'] = $tenantIdToUse;
        }

        return static::createClient([], ['headers' => $headers]);
    }

    /**
     * Generate a unique email address for testing
     */
    private function generateUniqueEmail(string $prefix = 'user'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Create a valid tenant for testing
     */
    private function createTenant(): string
    {
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/tenants', [
            'json' => [
                'name' => 'Test Tenant ' . uniqid(),
                'ownerEmail' => $this->generateUniqueEmail('tenant'),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        return $data['id'];
    }

    /**
     * Helper method to create a tax rule via the API
     *
     * @return array<string, mixed> The created tax rule data
     */
    private function createTaxRule(
        ?string $tenantId = null,
        string $name = 'France VAT Standard',
        string $countryCode = 'FR',
        float $ratePercentage = 20.0,
        ?string $regionCode = null
    ): array {
        $tenantId = $tenantId ?? $this->createTenant();
        $this->currentTenantId = $tenantId;

        $payload = [
            'tenantId' => $tenantId,
            'name' => $name,
            'countryCode' => $countryCode,
            'ratePercentage' => $ratePercentage,
        ];

        if ($regionCode !== null) {
            $payload['regionCode'] = $regionCode;
        }

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => $payload,
            ]);

        $this->assertResponseStatusCodeSame(201);

        return json_decode($response->getContent(), true);
    }

    // ===========================
    // POST /api/tax_rules - Create Tax Rule
    // ===========================

    public function testCreateTaxRuleSuccessfully(): void
    {
        $tenantId = $this->createTenant();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'tenantId' => $tenantId,
                    'name' => 'France VAT Standard',
                    'countryCode' => 'FR',
                    'ratePercentage' => 20.0,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertIsString($data['id']);
        $this->assertSame('France VAT Standard', $data['name']);
        $this->assertSame('FR', $data['countryCode']);
        $this->assertSame(20.0, $data['ratePercentage']);
        $this->assertTrue($data['isActive']);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testCreateTaxRuleForGermany(): void
    {
        $tenantId = $this->createTenant();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'tenantId' => $tenantId,
                    'name' => 'Germany VAT Standard',
                    'countryCode' => 'DE',
                    'ratePercentage' => 19.0,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('Germany VAT Standard', $data['name']);
        $this->assertSame('DE', $data['countryCode']);
        $this->assertSame(19.0, $data['ratePercentage']);
    }

    public function testCreateTaxRuleForRomania(): void
    {
        $tenantId = $this->createTenant();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'tenantId' => $tenantId,
                    'name' => 'Romania VAT Standard',
                    'countryCode' => 'RO',
                    'ratePercentage' => 19.0,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('Romania VAT Standard', $data['name']);
        $this->assertSame('RO', $data['countryCode']);
        $this->assertSame(19.0, $data['ratePercentage']);
    }

    public function testCreateTaxRuleWithRegionCode(): void
    {
        $tenantId = $this->createTenant();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'tenantId' => $tenantId,
                    'name' => 'California Sales Tax',
                    'countryCode' => 'US',
                    'regionCode' => 'CA',
                    'ratePercentage' => 7.25,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('California Sales Tax', $data['name']);
        $this->assertSame('US', $data['countryCode']);
        $this->assertSame('CA', $data['regionCode']);
        $this->assertSame(7.25, $data['ratePercentage']);
    }

    public function testCreateTaxRuleFailsWithoutAuthentication(): void
    {
        $tenantId = $this->createTenant();

        static::createClient()->request('POST', '/api/v1/tax_rules', [
            'json' => [
                'tenantId' => $tenantId,
                'name' => 'Test Tax Rule',
                'countryCode' => 'FR',
                'ratePercentage' => 20.0,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateTaxRuleFailsWithoutTenantId(): void
    {
        $response = $this->createAuthenticatedClient()
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'name' => 'Test Tax Rule',
                    'countryCode' => 'FR',
                    'ratePercentage' => 20.0,
                ],
            ]);

        // Should fail validation (422) or bad request (400)
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateTaxRuleFailsWithInvalidCountryCode(): void
    {
        $tenantId = $this->createTenant();

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'tenantId' => $tenantId,
                    'name' => 'Invalid Country',
                    'countryCode' => 'INVALID',  // Invalid ISO code
                    'ratePercentage' => 20.0,
                ],
            ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateTaxRuleFailsWithNegativeRate(): void
    {
        $tenantId = $this->createTenant();

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'tenantId' => $tenantId,
                    'name' => 'Negative Rate',
                    'countryCode' => 'FR',
                    'ratePercentage' => -5.0,  // Negative rate
                ],
            ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateTaxRuleFailsWithRateOver100(): void
    {
        $tenantId = $this->createTenant();

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_rules', [
                'json' => [
                    'tenantId' => $tenantId,
                    'name' => 'Over 100 Rate',
                    'countryCode' => 'FR',
                    'ratePercentage' => 150.0,  // Over 100%
                ],
            ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // ===========================
    // GET /api/tax_rules/{id} - Get Tax Rule by ID
    // ===========================

    public function testGetTaxRuleById(): void
    {
        $taxRule = $this->createTaxRule();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('GET', '/api/tax_rules/' . $taxRule['id']);

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($response->getContent(), true);

        $this->assertSame($taxRule['id'], $data['id']);
        $this->assertSame('France VAT Standard', $data['name']);
        $this->assertSame('FR', $data['countryCode']);
        $this->assertSame(20.0, $data['ratePercentage']);
    }

    public function testGetTaxRuleByIdFailsForNonExistentId(): void
    {
        $this->createAuthenticatedClient()
            ->request('GET', '/api/tax_rules/' . \Symfony\Component\Uid\Uuid::v4()->toString());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetTaxRuleByIdFailsWithoutAuthentication(): void
    {
        $taxRule = $this->createTaxRule();

        static::createClient()->request('GET', '/api/tax_rules/' . $taxRule['id']);

        $this->assertResponseStatusCodeSame(401);
    }

    // ===========================
    // GET /api/tax_rules - Get Tax Rules Collection
    // ===========================

    public function testGetTaxRulesCollection(): void
    {
        $tenantId = $this->createTenant();

        // Create multiple tax rules
        $this->createTaxRule($tenantId, 'France VAT', 'FR', 20.0);
        $this->createTaxRule($tenantId, 'Germany VAT', 'DE', 19.0);
        $this->createTaxRule($tenantId, 'Romania VAT', 'RO', 19.0);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('GET', '/api/v1/tax_rules');

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('hydra:totalItems', $data);
        $this->assertGreaterThanOrEqual(3, $data['hydra:totalItems']);

        // Verify structure of first item
        $firstItem = $data['hydra:member'][0];
        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('name', $firstItem);
        $this->assertArrayHasKey('countryCode', $firstItem);
        $this->assertArrayHasKey('ratePercentage', $firstItem);
    }

    public function testGetTaxRulesCollectionWithPagination(): void
    {
        $tenantId = $this->createTenant();

        // Create multiple tax rules to test pagination
        for ($i = 0; $i < 35; $i++) {
            $this->createTaxRule($tenantId, "Tax Rule $i", 'FR', 20.0);
        }

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('GET', '/api/tax_rules?page=1');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('hydra:view', $data);
        $this->assertCount(30, $data['hydra:member']); // Default 30 items per page
        $this->assertArrayHasKey('hydra:next', $data['hydra:view']);
    }

    public function testGetTaxRulesCollectionEmpty(): void
    {
        $tenantId = $this->createTenant();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('GET', '/api/v1/tax_rules');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertSame(0, $data['hydra:totalItems']);
    }

    public function testGetTaxRulesCollectionFailsWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/api/v1/tax_rules');

        $this->assertResponseStatusCodeSame(401);
    }

    // ===========================
    // PATCH /api/tax_rules/{id} - Update Tax Rule
    // ===========================

    public function testUpdateTaxRuleName(): void
    {
        $taxRule = $this->createTaxRule();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('PATCH', '/api/tax_rules/' . $taxRule['id'], [
                'headers' => ['content-type' => 'application/merge-patch+json'],
                'json' => [
                    'name' => 'France VAT Updated',
                ],
            ]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('France VAT Updated', $data['name']);
        $this->assertSame('FR', $data['countryCode']); // Unchanged
        $this->assertSame(20.0, $data['ratePercentage']); // Unchanged
    }

    public function testUpdateTaxRuleRate(): void
    {
        $taxRule = $this->createTaxRule();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('PATCH', '/api/tax_rules/' . $taxRule['id'], [
                'headers' => ['content-type' => 'application/merge-patch+json'],
                'json' => [
                    'ratePercentage' => 19.6,
                ],
            ]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(19.6, $data['ratePercentage']);
    }

    public function testUpdateTaxRuleMultipleFields(): void
    {
        $taxRule = $this->createTaxRule();

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('PATCH', '/api/tax_rules/' . $taxRule['id'], [
                'headers' => ['content-type' => 'application/merge-patch+json'],
                'json' => [
                    'name' => 'France VAT Reduced',
                    'ratePercentage' => 5.5,
                ],
            ]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('France VAT Reduced', $data['name']);
        $this->assertSame(5.5, $data['ratePercentage']);
    }

    public function testUpdateTaxRuleFailsWithInvalidRate(): void
    {
        $taxRule = $this->createTaxRule();

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('PATCH', '/api/tax_rules/' . $taxRule['id'], [
                'headers' => ['content-type' => 'application/merge-patch+json'],
                'json' => [
                    'ratePercentage' => -10.0,  // Negative
                ],
            ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateTaxRuleFailsWithoutAuthentication(): void
    {
        $taxRule = $this->createTaxRule();

        static::createClient()->request('PATCH', '/api/tax_rules/' . $taxRule['id'], [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'Updated Name',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ===========================
    // PATCH /api/tax_rules/{id}/deactivate - Deactivate Tax Rule
    // ===========================

    public function testDeactivateTaxRule(): void
    {
        $taxRule = $this->createTaxRule();

        // Verify initially active
        $this->assertTrue($taxRule['isActive']);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('PATCH', '/api/tax_rules/' . $taxRule['id'] . '/deactivate');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['isActive']);
    }

    public function testDeactivateAlreadyInactiveTaxRule(): void
    {
        $taxRule = $this->createTaxRule();

        // Deactivate once
        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('PATCH', '/api/tax_rules/' . $taxRule['id'] . '/deactivate');

        // Deactivate again - should succeed (idempotent)
        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $this->currentTenantId)
            ->request('PATCH', '/api/tax_rules/' . $taxRule['id'] . '/deactivate');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['isActive']);
    }

    public function testDeactivateTaxRuleFailsForNonExistentId(): void
    {
        $this->createAuthenticatedClient()
            ->request('PATCH', '/api/tax_rules/' . \Symfony\Component\Uid\Uuid::v4()->toString() . '/deactivate');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeactivateTaxRuleFailsWithoutAuthentication(): void
    {
        $taxRule = $this->createTaxRule();

        static::createClient()->request('PATCH', '/api/tax_rules/' . $taxRule['id'] . '/deactivate');

        $this->assertResponseStatusCodeSame(401);
    }

    // ===========================
    // Edge Cases & Multi-Tenancy
    // ===========================

    public function testTenantIsolation(): void
    {
        // Create tax rule for Tenant A
        $tenantA = $this->createTenant();
        $taxRuleA = $this->createTaxRule($tenantA, 'Tenant A VAT', 'FR', 20.0);

        // Create tax rule for Tenant B
        $tenantB = $this->createTenant();
        $taxRuleB = $this->createTaxRule($tenantB, 'Tenant B VAT', 'DE', 19.0);

        // Verify Tenant A cannot see Tenant B's tax rule
        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantA)
            ->request('GET', '/api/tax_rules/' . $taxRuleB['id']);

        // Should return 404 or 403 (not found because of tenant isolation)
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMultipleTaxRulesForSameCountry(): void
    {
        $tenantId = $this->createTenant();

        // Create multiple tax rules for France (different rates)
        $this->createTaxRule($tenantId, 'France VAT Standard', 'FR', 20.0);
        $this->createTaxRule($tenantId, 'France VAT Reduced', 'FR', 5.5);
        $this->createTaxRule($tenantId, 'France VAT Super Reduced', 'FR', 2.1);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('GET', '/api/v1/tax_rules');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        // Should have 3 rules for FR
        $frenchRules = array_filter($data['hydra:member'], fn($rule) => $rule['countryCode'] === 'FR');
        $this->assertCount(3, $frenchRules);
    }

    public function testEUVATRates(): void
    {
        $tenantId = $this->createTenant();

        // Create tax rules for multiple EU countries
        $euCountries = [
            ['name' => 'France VAT', 'code' => 'FR', 'rate' => 20.0],
            ['name' => 'Germany VAT', 'code' => 'DE', 'rate' => 19.0],
            ['name' => 'Romania VAT', 'code' => 'RO', 'rate' => 19.0],
            ['name' => 'Italy VAT', 'code' => 'IT', 'rate' => 22.0],
            ['name' => 'Spain VAT', 'code' => 'ES', 'rate' => 21.0],
        ];

        foreach ($euCountries as $country) {
            $this->createTaxRule($tenantId, $country['name'], $country['code'], $country['rate']);
        }

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('GET', '/api/v1/tax_rules');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true);

        $this->assertGreaterThanOrEqual(5, $data['hydra:totalItems']);
    }
}
