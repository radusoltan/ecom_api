<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

// Load files that contain multiple classes
require_once __DIR__ . '/../../../../src/Catalog/Domain/Model/ConfigurableProduct.php';
require_once __DIR__ . '/../../../../src/Catalog/Domain/Model/Option.php';
require_once __DIR__ . '/../../../../src/Catalog/Domain/Model/Variant.php';

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;

/**
 * Functional tests for Variant API endpoints
 */
final class VariantApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private TenantId $tenantId;
    private ConfigurableProductId $configurableProductId;
    private ProductId $productId;
    private ConfigurableProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Use default test tenant instead of random
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $this->productId = ProductId::generate();
        $this->configurableProductId = ConfigurableProductId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString());

        $container = static::getContainer();
        $this->repository = $container->get(ConfigurableProductRepositoryInterface::class);

        // Create a configurable product with options for testing
        $this->createTestConfigurableProduct();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Create a test configurable product with options
     */
    private function createTestConfigurableProduct(): void
    {
        $configurableProduct = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId
        );

        // Define color and size options
        $colorOption = Option::create(
            OptionId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            1,
            []
        );

        $sizeOption = Option::create(
            OptionId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            2,
            []
        );

        $configurableProduct->defineOption($colorOption);
        $configurableProduct->defineOption($sizeOption);

        $this->repository->save($configurableProduct);
    }

    /**
     * Create an authenticated client with JWT token
     */
    protected function createAuthenticatedClient(string $email = 'admin@admin.com', array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'])
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

        return static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'X-Tenant-ID' => $this->tenantId->toString(),
            ]
        ]);
    }

    /**
     * Test 1: Create a variant via POST /api/v1/variant_entities
     */
    public function testCreateVariant(): void
    {
        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'productId' => $this->productId->toString(),
                'sku' => 'SKU-TST-FUNC-001-red-small',
                'optionValueMap' => ['color' => 'red', 'size' => 'small'],
                'priceAmount' => 1999,
                'priceCurrency' => 'USD',
                'stockOnHand' => 10,
                'trackInventory' => true,
                'allowBackorder' => false,
                'isActive' => true,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('SKU-TST-FUNC-001-red-small', $data['sku']);
        $this->assertEquals(['color' => 'red', 'size' => 'small'], $data['optionValueMap']);
        $this->assertEquals(1999, $data['priceAmount']);
        $this->assertEquals('USD', $data['priceCurrency']);
        $this->assertEquals(10, $data['stockOnHand']);
        $this->assertTrue($data['isActive']);
    }

    /**
     * Test 2: Prevent duplicate variant combinations
     */
    public function testPreventDuplicateVariantCombinations(): void
    {
        // Create first variant
        $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $this->productId->toString(),
                'sku' => 'SKU-TST-FUNC-DUP1-blue-large',
                'optionValueMap' => ['color' => 'blue', 'size' => 'large'],
                'priceAmount' => 2499,
                'priceCurrency' => 'USD',
                'stockOnHand' => 5,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Try to create duplicate variant with same combination
        $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $this->productId->toString(),
                'sku' => 'SKU-TST-FUNC-DUP2-blue-large',
                'optionValueMap' => ['color' => 'blue', 'size' => 'large'], // Same combination!
                'priceAmount' => 2999,
                'priceCurrency' => 'USD',
                'stockOnHand' => 10,
            ],
        ]);

        // Should fail with 500 (domain exception)
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Test 3: Get variant collection via GET /api/v1/variant_entities
     */
    public function testGetVariantCollection(): void
    {
        // Create a variant first
        $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $this->productId->toString(),
                'sku' => 'SKU-TST-FUNC-003-green-medium',
                'optionValueMap' => ['color' => 'green', 'size' => 'medium'],
                'priceAmount' => 1799,
                'priceCurrency' => 'USD',
                'stockOnHand' => 15,
            ],
        ]);

        // Get collection with productId filter
        $response = $this->createAuthenticatedClient()->request(
            'GET',
            '/api/v1/variant_entities?productId=' . $this->productId->toString()
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        // Should be JSON-LD format with hydra:member
        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertGreaterThanOrEqual(1, count($data['hydra:member']));
    }

    /**
     * Test 4: Get single variant via GET /api/v1/variant_entities/{id}
     */
    public function testGetSingleVariant(): void
    {
        // Create a variant first
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $this->productId->toString(),
                'sku' => 'SKU-TST-FUNC-004-black-small',
                'optionValueMap' => ['color' => 'black', 'size' => 'small'],
                'priceAmount' => 2199,
                'priceCurrency' => 'USD',
                'stockOnHand' => 8,
            ],
        ]);

        $createData = $createResponse->toArray();
        $variantId = $createData['id'];

        // Get single variant
        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/variant_entities/' . $variantId);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertEquals($variantId, $data['id']);
        $this->assertEquals('SKU-TST-FUNC-004-black-small', $data['sku']);
        $this->assertEquals(['color' => 'black', 'size' => 'small'], $data['optionValueMap']);
    }

    /**
     * Test 5: Update variant via PATCH /api/v1/variant_entities/{id}
     */
    public function testUpdateVariant(): void
    {
        // Create a variant first
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $this->productId->toString(),
                'sku' => 'SKU-TST-FUNC-005-white-large',
                'optionValueMap' => ['color' => 'white', 'size' => 'large'],
                'priceAmount' => 3499,
                'priceCurrency' => 'USD',
                'stockOnHand' => 5,
            ],
        ]);

        $createData = $createResponse->toArray();
        $variantId = $createData['id'];

        // Update the variant
        $response = $this->createAuthenticatedClient()->request('PATCH', '/api/v1/variant_entities/' . $variantId, [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'priceAmount' => 3999, // Update price
                'stockOnHand' => 10, // Update stock
                'isActive' => false, // Deactivate
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = $response->toArray();

        $this->assertEquals(3999, $data['priceAmount']);
        $this->assertEquals(10, $data['stockOnHand']);
        $this->assertFalse($data['isActive']);
    }

    /**
     * Test 6: Delete variant via DELETE /api/v1/variant_entities/{id}
     */
    public function testDeleteVariant(): void
    {
        // Create a variant first
        $createResponse = $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $this->productId->toString(),
                'sku' => 'SKU-TST-FUNC-006-yellow-medium',
                'optionValueMap' => ['color' => 'yellow', 'size' => 'medium'],
                'priceAmount' => 1799,
                'priceCurrency' => 'USD',
                'stockOnHand' => 12,
            ],
        ]);

        $createData = $createResponse->toArray();
        $variantId = $createData['id'];

        // Delete the variant
        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/variant_entities/' . $variantId);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(204); // No content

        // Try to get the deleted variant - should fail
        $this->createAuthenticatedClient()->request('GET', '/api/v1/variant_entities/' . $variantId);

        $this->assertResponseStatusCodeSame(404); // Not found
    }

    /**
     * Test 7: Validate required fields
     */
    public function testValidateRequiredFields(): void
    {
        // Try to create variant without SKU (required field)
        $this->createAuthenticatedClient()->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $this->productId->toString(),
                // Missing 'sku'
                'optionValueMap' => ['color' => 'purple', 'size' => 'small'],
                'priceAmount' => 1999,
                'priceCurrency' => 'USD',
                'stockOnHand' => 10,
            ],
        ]);

        // Should fail with 500 (will be caught as exception)
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Test 8: Test tenant isolation
     */
    public function testTenantIsolation(): void
    {
        $tenant1Id = TenantId::generate();
        $tenant2Id = TenantId::generate();

        // Create ConfigurableProduct for tenant 1
        $productId1 = ProductId::generate();
        $cfgProductId1 = ConfigurableProductId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString());

        $cfgProduct1 = ConfigurableProduct::create($cfgProductId1, $productId1, $tenant1Id);
        $option1 = Option::create(
            OptionId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            1,
            []
        );
        $cfgProduct1->defineOption($option1);
        $this->repository->save($cfgProduct1);

        // Create variant for tenant 1
        $client1 = static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer ' . $this->getAuthToken(),
                'X-Tenant-ID' => $tenant1Id->toString(),
            ]
        ]);

        $response1 = $client1->request('POST', '/api/v1/variant_entities', [
            'json' => [
                'productId' => $productId1->toString(),
                'sku' => 'SKU-TENANT1-001',
                'optionValueMap' => ['color' => 'red'],
                'priceAmount' => 1000,
                'priceCurrency' => 'USD',
                'stockOnHand' => 5,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $tenant1VariantId = $response1->toArray()['id'];

        // Try to access tenant1's variant with tenant2's credentials
        $client2 = static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer ' . $this->getAuthToken(),
                'X-Tenant-ID' => $tenant2Id->toString(),
            ]
        ]);

        $client2->request('GET', '/api/v1/variant_entities/' . $tenant1VariantId);

        // Should fail with 404 (not found for this tenant)
        $this->assertResponseStatusCodeSame(404);
    }

    private function getAuthToken(): string
    {
        $container = static::getContainer();
        $encoder = $container->get('lexik_jwt_authentication.encoder');

        return $encoder->encode([
            'email' => 'admin@admin.com',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
            'iat' => time(),
            'exp' => time() + 3600,
        ]);
    }
}
