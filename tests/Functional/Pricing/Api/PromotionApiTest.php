<?php

declare(strict_types=1);

namespace App\Tests\Functional\Pricing\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Pricing\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMPromotionRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;

final class PromotionApiTest extends ApiTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineORMPromotionRepository $promotionRepository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->promotionRepository = $container->get(DoctrineORMPromotionRepository::class);

        $this->tenantId = TenantId::generate();

        // Clean up database - only if table exists
        try {
            $this->entityManager->getConnection()->executeStatement('DELETE FROM promotions WHERE 1=1');
            $this->entityManager->clear();
        } catch (\Exception $e) {
            // Table might not exist yet, ignore
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->entityManager->getConnection()->executeStatement('DELETE FROM promotions WHERE 1=1');
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }

        $this->entityManager->close();
    }

    /**
     * Create an authenticated client with JWT token for API testing.
     */
    protected function createAuthenticatedClient(string $email = 'test@example.com', array $roles = ['ROLE_USER'])
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
            $userEntity->setUsername(explode('@', $email)[0].'-'.bin2hex(random_bytes(4)));
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
        return static::createClient([], ['headers' => ['authorization' => 'Bearer '.$token]]);
    }

    // ==================== POST /api/promotions ====================

    public function testCreateCartRulePromotion(): void
    {
        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Summer Cart Sale',
                'type' => 'cart_rule',
                'discountType' => 'percentage',
                'discountValue' => 20.0,
                'priority' => 100,
                'conditions' => ['min_cart_value' => 50],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Summer Cart Sale', $data['name']);
        $this->assertSame('cart_rule', $data['type']);
        $this->assertSame('percentage', $data['discountType']);
        $this->assertEquals(20.0, $data['discountValue']); // Changed to assertEquals to allow int/float
        $this->assertSame(100, $data['priority']);
        $this->assertArrayHasKey('active', $data);
        $this->assertFalse($data['active']);
        $this->assertNull($data['couponCode']);
        $this->assertSame(['min_cart_value' => 50], $data['conditions']);
    }

    public function testCreateCatalogRulePromotion(): void
    {
        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Electronics Discount',
                'type' => 'catalog_rule',
                'discountType' => 'fixed_amount',
                'discountValue' => 15.0,
                'priority' => 150,
                'conditions' => ['category' => 'electronics'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray();

        $this->assertSame('Electronics Discount', $data['name']);
        $this->assertSame('catalog_rule', $data['type']);
        $this->assertSame('fixed_amount', $data['discountType']);
        $this->assertEquals(15.0, $data['discountValue']); // Changed to assertEquals to allow int/float
        $this->assertSame(150, $data['priority']);
    }

    public function testCreateCouponPromotion(): void
    {
        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Welcome Coupon',
                'type' => 'coupon',
                'discountType' => 'percentage',
                'discountValue' => 10.0,
                'priority' => 50,
                'couponCode' => 'WELCOME10',
                'conditions' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray();

        $this->assertSame('Welcome Coupon', $data['name']);
        $this->assertSame('coupon', $data['type']);
        $this->assertSame('WELCOME10', $data['couponCode']);
    }

    public function testCreatePromotionWithValidityDates(): void
    {
        $client = $this->createAuthenticatedClient();

        $validFrom = '2025-06-01T00:00:00+00:00';
        $validTo = '2025-08-31T23:59:59+00:00';

        $response = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Summer Sale 2025',
                'type' => 'cart_rule',
                'discountType' => 'percentage',
                'discountValue' => 25.0,
                'priority' => 200,
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'conditions' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray();

        $this->assertSame('Summer Sale 2025', $data['name']);
        $this->assertArrayHasKey('validFrom', $data);
        $this->assertArrayHasKey('validTo', $data);
    }

    public function testCreatePromotionValidatesNameLength(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'AB', // Too short (min 3 chars)
                'type' => 'cart_rule',
                'discountType' => 'percentage',
                'discountValue' => 10.0,
                'priority' => 100,
                'conditions' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreatePromotionValidatesPriority(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Invalid Priority',
                'type' => 'cart_rule',
                'discountType' => 'percentage',
                'discountValue' => 10.0,
                'priority' => 1001, // Too high (max 1000)
                'conditions' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateCouponPromotionRequiresCouponCode(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Coupon Without Code',
                'type' => 'coupon',
                'discountType' => 'percentage',
                'discountValue' => 10.0,
                'priority' => 100,
                'conditions' => [],
                // Missing couponCode
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // ==================== GET /api/promotions/{id} ====================

    public function testGetPromotionById(): void
    {
        // Create a promotion first
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(15.0),
            priority: 120
        );

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('GET', '/api/v1/promotions/'.$promotion->id()->toString(), [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertSame($promotion->id()->toString(), $data['id']);
        $this->assertSame('Test Promotion', $data['name']);
        $this->assertSame('cart_rule', $data['type']);
        $this->assertEquals(15.0, $data['discountValue']);
        $this->assertSame(120, $data['priority']);
    }

    public function testGetPromotionByIdNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/v1/promotions/01HQZXJ9K3M5N6P7Q8R9S0T1U2', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // ==================== GET /api/promotions ====================

    public function testGetPromotionCollection(): void
    {
        // Create multiple promotions
        $promotion1 = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Promotion 1',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $promotion2 = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Promotion 2',
            type: PromotionType::catalogRule(),
            discount: Discount::fixedAmount(5.0)
        );

        $promotion3 = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Promotion 3',
            type: PromotionType::coupon(),
            discount: Discount::percentage(20.0),
            couponCode: CouponCode::fromString('SAVE20')
        );

        $this->promotionRepository->save($promotion1);
        $this->promotionRepository->save($promotion2);
        $this->promotionRepository->save($promotion3);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('GET', '/api/v1/promotions', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        // Check Hydra format or direct member access
        $members = $data['member'] ?? $data['hydra:member'] ?? [];

        $this->assertGreaterThanOrEqual(3, count($members));

        // Verify promotion names are present
        $names = array_column($members, 'name');
        $this->assertContains('Promotion 1', $names);
        $this->assertContains('Promotion 2', $names);
        $this->assertContains('Promotion 3', $names);
    }

    public function testGetPromotionCollectionIsEmptyForDifferentTenant(): void
    {
        // Create promotion for first tenant
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Tenant 1 Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        // Request with different tenant
        $differentTenantId = TenantId::generate();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('GET', '/api/v1/promotions', [
            'headers' => [
                'X-Tenant-ID' => $differentTenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();
        $members = $data['member'] ?? $data['hydra:member'] ?? [];

        $this->assertCount(0, $members);
    }

    // ==================== PUT /api/promotions/{id} ====================

    public function testUpdatePromotion(): void
    {
        // Create a promotion first
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Original Name',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            priority: 100
        );

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('PUT', '/api/v1/promotions/'.$promotion->id()->toString(), [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Updated Name',
                'discountType' => 'percentage',
                'discountValue' => 25.0,
                'priority' => 200,
                'conditions' => ['new_condition' => 'value'],
                'validFrom' => null,
                'validTo' => null,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertSame('Updated Name', $data['name']);
        $this->assertEquals(25.0, $data['discountValue']); // Changed to assertEquals to allow int/float
        $this->assertSame(200, $data['priority']);
        $this->assertSame(['new_condition' => 'value'], $data['conditions']);
    }

    public function testUpdatePromotionNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('PUT', '/api/v1/promotions/01HQZXJ9K3M5N6P7Q8R9S0T1U2', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Updated Name',
                'discountType' => 'percentage',
                'discountValue' => 25.0,
                'priority' => 200,
                'conditions' => [],
                'validFrom' => null,
                'validTo' => null,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // ==================== PATCH /api/promotions/{id}/activate ====================

    public function testActivatePromotion(): void
    {
        // Create an inactive promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Inactive Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $this->assertFalse($promotion->isActive());

        $client = $this->createAuthenticatedClient();

        $response = $client->request('PATCH', '/api/v1/promotions/'.$promotion->id()->toString().'/activate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertArrayHasKey('active', $data);
        $this->assertTrue($data['active']);
    }

    public function testActivateAlreadyActivePromotionFails(): void
    {
        // Create an active promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Active Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $promotion->activate();

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $client->request('PATCH', '/api/v1/promotions/'.$promotion->id()->toString().'/activate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(500); // Domain exception
    }

    // ==================== PATCH /api/promotions/{id}/deactivate ====================

    public function testDeactivatePromotion(): void
    {
        // Create an active promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Active Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $promotion->activate();

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $this->assertTrue($promotion->isActive());

        $client = $this->createAuthenticatedClient();

        $response = $client->request('PATCH', '/api/v1/promotions/'.$promotion->id()->toString().'/deactivate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertArrayHasKey('active', $data);
        $this->assertFalse($data['active']);
    }

    public function testDeactivateAlreadyInactivePromotionFails(): void
    {
        // Create an inactive promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Inactive Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $client->request('PATCH', '/api/v1/promotions/'.$promotion->id()->toString().'/deactivate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(500); // Domain exception
    }

    // ==================== Complete Lifecycle Tests ====================

    public function testCompletePromotionLifecycle(): void
    {
        $client = $this->createAuthenticatedClient();

        // 1. Create promotion
        $createResponse = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Lifecycle Test Promotion',
                'type' => 'cart_rule',
                'discountType' => 'percentage',
                'discountValue' => 15.0,
                'priority' => 100,
                'conditions' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $createData = $createResponse->toArray();
        $promotionId = $createData['id'];
        $this->assertArrayHasKey('active', $createData);
        $this->assertFalse($createData['active']);

        // 2. Get promotion (verify creation)
        $getResponse = $client->request('GET', '/api/v1/promotions/'.$promotionId, [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $getData = $getResponse->toArray();
        $this->assertSame('Lifecycle Test Promotion', $getData['name']);

        // 3. Activate promotion
        $activateResponse = $client->request('PATCH', '/api/v1/promotions/'.$promotionId.'/activate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $activateData = $activateResponse->toArray();
        $this->assertArrayHasKey('active', $activateData);
        $this->assertTrue($activateData['active']);

        // 4. Update promotion
        $updateResponse = $client->request('PUT', '/api/v1/promotions/'.$promotionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Updated Lifecycle Promotion',
                'discountType' => 'percentage',
                'discountValue' => 30.0,
                'priority' => 250,
                'conditions' => ['updated' => true],
                'validFrom' => null,
                'validTo' => null,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $updateData = $updateResponse->toArray();
        $this->assertSame('Updated Lifecycle Promotion', $updateData['name']);
        $this->assertEquals(30.0, $updateData['discountValue']);

        // 5. Deactivate promotion
        $deactivateResponse = $client->request('PATCH', '/api/v1/promotions/'.$promotionId.'/deactivate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $deactivateData = $deactivateResponse->toArray();
        $this->assertArrayHasKey('active', $deactivateData);
        $this->assertFalse($deactivateData['active']);

        // 6. Final verification
        $finalResponse = $client->request('GET', '/api/v1/promotions/'.$promotionId, [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $finalData = $finalResponse->toArray();
        $this->assertSame('Updated Lifecycle Promotion', $finalData['name']);
        $this->assertEquals(30.0, $finalData['discountValue']);
        $this->assertArrayHasKey('active', $finalData);
        $this->assertFalse($finalData['active']);
    }

    public function testPromotionStackingScenario(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create 3 different promotion types
        // 1. Cart Rule (highest priority in stacking)
        $cartRuleResponse = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Cart Rule 20% Off',
                'type' => 'cart_rule',
                'discountType' => 'percentage',
                'discountValue' => 20.0,
                'priority' => 300,
                'conditions' => [],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $cartRuleData = $cartRuleResponse->toArray();

        // 2. Catalog Rule
        $catalogRuleResponse = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Catalog 10% Off',
                'type' => 'catalog_rule',
                'discountType' => 'percentage',
                'discountValue' => 10.0,
                'priority' => 200,
                'conditions' => [],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $catalogRuleData = $catalogRuleResponse->toArray();

        // 3. Coupon (lowest priority in stacking)
        $couponResponse = $client->request('POST', '/api/v1/promotions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'name' => 'Coupon 5% Off',
                'type' => 'coupon',
                'discountType' => 'percentage',
                'discountValue' => 5.0,
                'priority' => 100,
                'couponCode' => 'SAVE5',
                'conditions' => [],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $couponData = $couponResponse->toArray();

        // Activate all promotions
        $client->request('PATCH', '/api/v1/promotions/'.$cartRuleData['id'].'/activate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $client->request('PATCH', '/api/v1/promotions/'.$catalogRuleData['id'].'/activate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $client->request('PATCH', '/api/v1/promotions/'.$couponData['id'].'/activate', [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Get all promotions and verify all 3 are active
        $listResponse = $client->request('GET', '/api/v1/promotions', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $listData = $listResponse->toArray();
        $members = $listData['member'] ?? $listData['hydra:member'] ?? [];

        $this->assertGreaterThanOrEqual(3, count($members));

        // Verify we have all 3 types
        $types = array_column($members, 'type');
        $this->assertContains('cart_rule', $types);
        $this->assertContains('catalog_rule', $types);
        $this->assertContains('coupon', $types);
    }

    // ==================== GET /api/promotions/active ====================

    public function testGetActivePromotionsOnly(): void
    {
        // Create mixed active/inactive promotions
        $activePromotion1 = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Active Promotion 1',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );
        $activePromotion1->activate();

        $activePromotion2 = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Active Promotion 2',
            type: PromotionType::catalogRule(),
            discount: Discount::percentage(15.0)
        );
        $activePromotion2->activate();

        $inactivePromotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Inactive Promotion',
            type: PromotionType::coupon(),
            discount: Discount::percentage(20.0),
            couponCode: CouponCode::fromString('INACTIVE20')
        );

        $this->promotionRepository->save($activePromotion1);
        $this->promotionRepository->save($activePromotion2);
        $this->promotionRepository->save($inactivePromotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('GET', '/api/v1/active-promotions', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        // Controller returns direct array (not Hydra format)
        $members = is_array($data) && isset($data[0]) ? $data : ($data['member'] ?? $data['hydra:member'] ?? []);

        // Should only return 2 active promotions
        $this->assertCount(2, $members);

        // Verify all returned promotions are active
        foreach ($members as $promotion) {
            $this->assertArrayHasKey('active', $promotion);
            $this->assertTrue($promotion['active']);
        }

        // Verify names
        $names = array_column($members, 'name');
        $this->assertContains('Active Promotion 1', $names);
        $this->assertContains('Active Promotion 2', $names);
        $this->assertNotContains('Inactive Promotion', $names);
    }

    // ==================== POST /api/promotions/preview ====================

    public function testPreviewPromotionWithoutCoupon(): void
    {
        // Create and activate a cart rule promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Preview Test 20% Off',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            priority: 100
        );
        $promotion->activate();

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/promotions/preview', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'subtotal' => 10000, // $100.00 in cents
                'currency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);

        $this->assertArrayHasKey('preview', $data);
        $preview = $data['preview'];

        $this->assertSame(10000, $preview['originalSubtotal']);
        $this->assertSame(2000, $preview['totalDiscount']); // 20% of 10000
        $this->assertSame(8000, $preview['finalPrice']);
        $this->assertSame('USD', $preview['currency']);
        $this->assertSame(1, $preview['promotionsCount']);
        $this->assertEquals(20.0, $preview['savingsPercentage']);
    }

    public function testPreviewPromotionWithCoupon(): void
    {
        // Create and activate a coupon promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Preview Coupon 15% Off',
            type: PromotionType::coupon(),
            discount: Discount::percentage(15.0),
            priority: 100,
            couponCode: CouponCode::fromString('PREVIEW15')
        );
        $promotion->activate();

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/promotions/preview', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'subtotal' => 20000, // $200.00 in cents
                'currency' => 'USD',
                'couponCode' => 'PREVIEW15',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertTrue($data['success']);

        $preview = $data['preview'];

        $this->assertSame(20000, $preview['originalSubtotal']);
        $this->assertSame(3000, $preview['totalDiscount']); // 15% of 20000
        $this->assertSame(17000, $preview['finalPrice']);
        $this->assertSame(1, $preview['promotionsCount']);
        $this->assertEquals(15.0, $preview['savingsPercentage']);
    }

    public function testPreviewPromotionWithNoApplicablePromotions(): void
    {
        // Create an inactive promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Inactive Preview Promotion',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0)
        );

        $this->promotionRepository->save($promotion);
        $this->entityManager->flush();

        $client = $this->createAuthenticatedClient();

        $response = $client->request('POST', '/api/v1/promotions/preview', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'subtotal' => 10000,
                'currency' => 'USD',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertTrue($data['success']);

        $preview = $data['preview'];

        $this->assertSame(10000, $preview['originalSubtotal']);
        $this->assertSame(0, $preview['totalDiscount']);
        $this->assertSame(10000, $preview['finalPrice']);
        $this->assertSame(0, $preview['promotionsCount']);
        $this->assertEquals(0.0, $preview['savingsPercentage']);
        $this->assertStringContainsString('No promotions applicable', $data['message']);
    }

    public function testPreviewPromotionRequiresSubtotal(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/promotions/preview', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'currency' => 'USD',
                // Missing subtotal
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
