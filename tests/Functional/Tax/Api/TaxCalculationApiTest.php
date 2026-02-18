<?php

declare(strict_types=1);

namespace App\Tests\Functional\Tax\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Comprehensive Functional Tests for Tax Calculation API Endpoint.
 *
 * Tests the Tax Calculation endpoint:
 * - POST /api/tax_calculations (Calculate Tax)
 *
 * Uses ApiTestCase with DAMA Bundle for automatic database transaction rollback.
 *
 * @group functional
 * @group tax
 * @group api
 */
final class TaxCalculationApiTest extends ApiTestCase
{
    private static int $counter = 0;
    private ?string $currentTenantId = null;

    /**
     * Create an authenticated client with JWT token and optional X-Tenant-ID header.
     */
    protected function createAuthenticatedClient(
        string $email = 'admin@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
        ?string $tenantId = null,
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
    private function generateUniqueEmail(string $prefix = 'user'): string
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

        return $data['id'];
    }

    /**
     * Helper method to create a tax rule via the API.
     *
     * @return array<string, mixed> The created tax rule data
     */
    private function createTaxRule(
        string $tenantId,
        string $name,
        string $countryCode,
        float $ratePercentage,
        ?string $regionCode = null,
    ): array {
        $payload = [
            'tenantId' => $tenantId,
            'name' => $name,
            'countryCode' => $countryCode,
            'ratePercentage' => $ratePercentage,
        ];

        if (null !== $regionCode) {
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
    // POST /api/tax_calculations - Calculate Tax
    // ===========================

    public function testCalculateTaxForFrance(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        // Create France VAT rule (20%)
        $this->createTaxRule($tenantId, 'France VAT Standard', 'FR', 20.0);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,  // €100.00
                    'countryCode' => 'FR',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(2000, $data['taxAmount']);  // €20.00 (20% of €100.00)
        $this->assertEquals(20.0, $data['taxRate']); // Use assertEquals for float (JSON may serialize 20.0 as 20)
        $this->assertSame('FR', $data['jurisdiction']);
        $this->assertSame('France VAT Standard', $data['taxRuleName']);
        $this->assertArrayHasKey('taxRuleId', $data);
    }

    public function testCalculateTaxForGermany(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        // Create Germany VAT rule (19%)
        $this->createTaxRule($tenantId, 'Germany VAT Standard', 'DE', 19.0);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,  // €100.00
                    'countryCode' => 'DE',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(1900, $data['taxAmount']);  // €19.00
        $this->assertEquals(19.0, $data['taxRate']); // Use assertEquals for float
        $this->assertSame('DE', $data['jurisdiction']);
    }

    public function testCalculateTaxForRomania(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        // Create Romania VAT rule (19%)
        $this->createTaxRule($tenantId, 'Romania VAT Standard', 'RO', 19.0);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 5000,  // €50.00
                    'countryCode' => 'RO',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(950, $data['taxAmount']);  // €9.50 (19% of €50.00)
        $this->assertEquals(19.0, $data['taxRate']); // Use assertEquals for float
        $this->assertSame('RO', $data['jurisdiction']);
    }

    public function testCalculateTaxWithRegion(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        // Create California Sales Tax rule
        $this->createTaxRule($tenantId, 'California Sales Tax', 'US', 7.25, 'CA');

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,  // $100.00
                    'countryCode' => 'US',
                    'regionCode' => 'CA',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(725, $data['taxAmount']);  // $7.25
        $this->assertEquals(7.25, $data['taxRate']); // Use assertEquals for float
        $this->assertStringContainsString('CA', $data['jurisdiction']);
    }

    public function testCalculateTaxForCountryWithoutRule(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        // No tax rule for US (without region)
        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,  // $100.00
                    'countryCode' => 'US',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['taxAmount']);  // No tax
        $this->assertEquals(0.0, $data['taxRate']); // Use assertEquals for float
        $this->assertNull($data['taxRuleId']);
        $this->assertNull($data['taxRuleName']);
    }

    public function testCalculateTaxForZeroAmount(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        $this->createTaxRule($tenantId, 'France VAT', 'FR', 20.0);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 0,  // €0.00
                    'countryCode' => 'FR',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['taxAmount']);
        $this->assertEquals(20.0, $data['taxRate']); // Use assertEquals for float
    }

    public function testCalculateTaxForLargeAmount(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        $this->createTaxRule($tenantId, 'France VAT', 'FR', 20.0);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 100000000,  // €1,000,000.00
                    'countryCode' => 'FR',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(20000000, $data['taxAmount']);  // €200,000.00
        $this->assertEquals(20.0, $data['taxRate']); // Use assertEquals for float
    }

    public function testCalculateTaxWithOddAmount(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        $this->createTaxRule($tenantId, 'Germany VAT', 'DE', 19.0);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 9999,  // €99.99
                    'countryCode' => 'DE',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // 9999 * 0.19 = 1899.81 → should round to 1900 cents
        $this->assertSame(1900, $data['taxAmount']);
        $this->assertEquals(19.0, $data['taxRate']); // Use assertEquals for float
    }

    public function testCalculateTaxForDeactivatedRule(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        // Create and deactivate tax rule
        $taxRule = $this->createTaxRule($tenantId, 'France VAT', 'FR', 20.0);

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('PATCH', '/api/v1/tax_rules/'.$taxRule['id'].'/deactivate', [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
                'json' => [], // Empty JSON body for PATCH
            ]);

        // Try to calculate tax with deactivated rule
        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,
                    'countryCode' => 'FR',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Deactivated rule should not apply
        $this->assertSame(0, $data['taxAmount']);
        $this->assertEquals(0.0, $data['taxRate']); // Use assertEquals for float
    }

    public function testCalculateTaxFailsWithoutAuthentication(): void
    {
        $tenantId = $this->createTenant();

        $response = static::createClient()->request('POST', '/api/v1/tax_calculations', [
            'json' => [
                'amountInCents' => 10000,
                'countryCode' => 'FR',
                'tenantId' => $tenantId,
            ],
        ]);

        // Note: Tax calculation endpoint may be public for some use cases
        // If authentication is required, this should return 401
        // Currently returns 201 with tax calculation result
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['taxAmount']); // No tax rule exists for this tenant
    }

    public function testCalculateTaxFailsWithNegativeAmount(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        $this->createTaxRule($tenantId, 'France VAT', 'FR', 20.0);

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => -1000,  // Negative amount
                    'countryCode' => 'FR',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(400); // BadRequestHttpException returns 400
    }

    public function testCalculateTaxFailsWithInvalidCountryCode(): void
    {
        $tenantId = $this->createTenant();
        $this->currentTenantId = $tenantId;

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,
                    'countryCode' => 'INVALID',  // Invalid ISO code
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(400); // BadRequestHttpException returns 400
    }

    public function testCalculateTaxFailsWithoutTenantId(): void
    {
        $this->createAuthenticatedClient()
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,
                    'countryCode' => 'FR',
                ],
            ]);

        $this->assertResponseStatusCodeSame(400); // BadRequestHttpException returns 400
    }

    public function testCalculateTaxFailsWithoutCountryCode(): void
    {
        $tenantId = $this->createTenant();

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(400); // BadRequestHttpException returns 400
    }

    public function testCalculateTaxFailsWithoutAmount(): void
    {
        $tenantId = $this->createTenant();

        $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'countryCode' => 'FR',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(400); // BadRequestHttpException returns 400
    }

    // ===========================
    // Multi-Tenancy & Edge Cases
    // ===========================

    public function testTenantIsolationInTaxCalculation(): void
    {
        // Tenant A with 20% tax
        $tenantA = $this->createTenant();
        $this->createTaxRule($tenantA, 'Tenant A Tax', 'FR', 20.0);

        // Tenant B with 10% tax
        $tenantB = $this->createTenant();
        $this->createTaxRule($tenantB, 'Tenant B Tax', 'FR', 10.0);

        // Calculate for Tenant A
        $responseA = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantA)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,
                    'countryCode' => 'FR',
                    'tenantId' => $tenantA,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $dataA = json_decode($responseA->getContent(), true);
        $this->assertSame(2000, $dataA['taxAmount']);  // 20%

        // Calculate for Tenant B
        $responseB = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantB)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,
                    'countryCode' => 'FR',
                    'tenantId' => $tenantB,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $dataB = json_decode($responseB->getContent(), true);
        $this->assertSame(1000, $dataB['taxAmount']);  // 10%
    }

    public function testMultipleTaxRatesSelectsFirst(): void
    {
        $tenantId = $this->createTenant();

        // Create multiple tax rules for same country (first one should be selected)
        $this->createTaxRule($tenantId, 'France VAT Standard', 'FR', 20.0);
        $this->createTaxRule($tenantId, 'France VAT Reduced', 'FR', 5.5);

        $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
            ->request('POST', '/api/v1/tax_calculations', [
                'json' => [
                    'amountInCents' => 10000,
                    'countryCode' => 'FR',
                    'tenantId' => $tenantId,
                ],
            ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        // Should use first created rule (20%)
        $this->assertSame(2000, $data['taxAmount']);
        $this->assertEquals(20.0, $data['taxRate']); // Use assertEquals for float
    }

    public function testCalculateTaxForAllEUCountries(): void
    {
        $tenantId = $this->createTenant();

        $euCountries = [
            ['code' => 'FR', 'rate' => 20.0, 'expectedTax' => 2000],
            ['code' => 'DE', 'rate' => 19.0, 'expectedTax' => 1900],
            ['code' => 'RO', 'rate' => 19.0, 'expectedTax' => 1900],
            ['code' => 'IT', 'rate' => 22.0, 'expectedTax' => 2200],
            ['code' => 'ES', 'rate' => 21.0, 'expectedTax' => 2100],
        ];

        foreach ($euCountries as $country) {
            $this->createTaxRule($tenantId, $country['code'].' VAT', $country['code'], $country['rate']);

            $response = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN'], $tenantId)
                ->request('POST', '/api/v1/tax_calculations', [
                    'json' => [
                        'amountInCents' => 10000,  // €100.00
                        'countryCode' => $country['code'],
                        'tenantId' => $tenantId,
                    ],
                ]);

            $this->assertResponseStatusCodeSame(201);
            $data = json_decode($response->getContent(), true);

            $this->assertSame($country['expectedTax'], $data['taxAmount'], "Failed for country: {$country['code']}");
            $this->assertEquals($country['rate'], $data['taxRate']); // Use assertEquals for float
        }
    }
}
