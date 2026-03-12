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
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final class CouponValidationApiTest extends ApiTestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private EntityManagerInterface $entityManager;
    private DoctrineORMPromotionRepository $promotionRepository;
    private TenantContext $tenantContext;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->promotionRepository = $container->get(DoctrineORMPromotionRepository::class);
        $this->tenantContext = $container->get(TenantContext::class);

        $this->tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);

        // Set tenant context
        $this->tenantContext->setCurrentTenant($this->tenantId);

        // Clean up database
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

    protected function createAuthenticatedClient(string $email = 'test@example.com', array $roles = ['ROLE_USER'])
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

        return static::createClient([], ['headers' => ['authorization' => 'Bearer '.$token]]);
    }

    public function testValidateCouponSuccessfully(): void
    {
        // Given: An active promotion with coupon code
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Summer Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(20.0),
            couponCode: CouponCode::fromString('SUMMER20')
        );
        $promotion->activate();
        $this->promotionRepository->save($promotion);

        // When: Validating the coupon
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'SUMMER20',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Coupon is valid with correct discount
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertTrue($data['valid']);
        $this->assertStringContainsString('applied successfully', $data['message']);
        $this->assertNotNull($data['promotion']);
        $this->assertEquals('Summer Sale', $data['promotion']['name']);
        $this->assertNotNull($data['discount_amount']);
        $this->assertEquals('2000', $data['discount_amount']['amount']); // 20% of 10000 cents
        $this->assertEquals('USD', $data['discount_amount']['currency']);
    }

    public function testValidateCouponNotFound(): void
    {
        // When: Validating a non-existent coupon
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'INVALID',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Coupon is invalid
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertFalse($data['valid']);
        $this->assertEquals('Coupon code not found', $data['message']);
        $this->assertNull($data['promotion']);
        $this->assertNull($data['discount_amount']);
    }

    public function testValidateCouponNotActive(): void
    {
        // Given: An inactive promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Winter Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(15.0),
            couponCode: CouponCode::fromString('WINTER15')
        );
        // Don't activate
        $this->promotionRepository->save($promotion);

        // When: Validating the coupon
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'WINTER15',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Coupon is invalid
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertFalse($data['valid']);
        $this->assertEquals('Coupon is not active', $data['message']);
    }

    public function testValidateCouponExpired(): void
    {
        // Given: An expired promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Past Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(10.0),
            couponCode: CouponCode::fromString('PAST10'),
            validFrom: new \DateTimeImmutable('2023-01-01'),
            validTo: new \DateTimeImmutable('2023-12-31')
        );
        $promotion->activate();
        $this->promotionRepository->save($promotion);

        // When: Validating the coupon
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'PAST10',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Coupon is invalid
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertFalse($data['valid']);
        $this->assertEquals('Coupon has expired', $data['message']);
    }

    public function testValidateCouponNotYetValid(): void
    {
        // Given: A future promotion
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Future Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(25.0),
            couponCode: CouponCode::fromString('FUTURE25'),
            validFrom: new \DateTimeImmutable('+1 month'),
            validTo: new \DateTimeImmutable('+2 months')
        );
        $promotion->activate();
        $this->promotionRepository->save($promotion);

        // When: Validating the coupon
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'FUTURE25',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Coupon is invalid
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertFalse($data['valid']);
        $this->assertStringContainsString('not yet valid', $data['message']);
    }

    public function testValidateCouponMinimumPurchaseNotMet(): void
    {
        // Given: A promotion with minimum purchase requirement
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Big Spender Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(30.0),
            couponCode: CouponCode::fromString('BIG30'),
            conditions: ['min_purchase_amount' => 200.00]
        );
        $promotion->activate();
        $this->promotionRepository->save($promotion);

        // When: Validating with cart total below minimum
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'BIG30',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Coupon is invalid
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertFalse($data['valid']);
        $this->assertStringContainsString('Minimum purchase amount', $data['message']);
    }

    public function testValidateCouponWithMaxDiscountLimit(): void
    {
        // Given: A promotion with max discount limit
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Limited Discount',
            type: PromotionType::coupon(),
            discount: Discount::percentage(50.0),
            couponCode: CouponCode::fromString('LIMITED50'),
            conditions: ['max_discount_amount' => 25.00]
        );
        $promotion->activate();
        $this->promotionRepository->save($promotion);

        // When: Validating with large cart (discount would be $50, but capped at $25)
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'LIMITED50',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Discount is capped at max amount
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertTrue($data['valid']);
        $this->assertEquals('2500', $data['discount_amount']['amount']); // Capped at $25
    }

    public function testValidateCouponWithFixedDiscount(): void
    {
        // Given: A promotion with fixed discount
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Fixed Discount',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(1500, 'USD'),
            couponCode: CouponCode::fromString('FIXED15')
        );
        $promotion->activate();
        $this->promotionRepository->save($promotion);

        // When: Validating the coupon
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'FIXED15',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Fixed discount is applied
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertTrue($data['valid']);
        $this->assertEquals('1500', $data['discount_amount']['amount']); // $15 fixed
    }

    public function testValidateCouponMissingCode(): void
    {
        // When: Sending request without code
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Bad request
        $this->assertResponseStatusCodeSame(400);
    }

    public function testValidateCouponMissingCartTotal(): void
    {
        // When: Sending request without cart total
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'TEST',
            ],
        ]);

        // Then: Bad request
        $this->assertResponseStatusCodeSame(400);
    }

    public function testValidateCouponInvalidCouponCodeFormat(): void
    {
        // When: Sending request with invalid coupon code format
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'ab', // Too short
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Bad request
        $this->assertResponseStatusCodeSame(400);
    }

    public function testValidateCouponUsageLimitReached(): void
    {
        // Given: A promotion with usage limit reached
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Limited Uses',
            type: PromotionType::coupon(),
            discount: Discount::percentage(10.0),
            couponCode: CouponCode::fromString('LIMIT10'),
            conditions: [
                'max_uses' => 100,
                'current_uses' => 100,
            ]
        );
        $promotion->activate();
        $this->promotionRepository->save($promotion);

        // When: Validating the coupon
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/coupons/validate', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'code' => 'LIMIT10',
                'cart_total' => [
                    'amount' => '100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        // Then: Coupon is invalid
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertFalse($data['valid']);
        $this->assertStringContainsString('usage limit', $data['message']);
    }
}
