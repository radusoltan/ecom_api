<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Repository;

// Load files that contain multiple classes
require_once __DIR__.'/../../../../../src/Catalog/Domain/Model/ConfigurableProduct.php';
require_once __DIR__.'/../../../../../src/Catalog/Domain/Model/Option.php';
require_once __DIR__.'/../../../../../src/Catalog/Domain/Model/Variant.php';

use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for ConfigurableProduct repository.
 */
final class ConfigurableProductRepositoryTest extends KernelTestCase
{
    private ConfigurableProductRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->repository = $container->get(ConfigurableProductRepositoryInterface::class);
        $this->tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        // Clean up test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Delete test configurable products for test tenant
        $qb = $em->createQueryBuilder();
        $qb->delete(\App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ConfigurableProductEntity::class, 'cp')
            ->where('cp.tenantId = :tenantId')
            ->setParameter('tenantId', '9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab')
            ->getQuery()
            ->execute();
    }

    /**
     * Test that we can save and retrieve a configurable product with options and variants.
     */
    public function testSaveAndRetrieveWithOptionsAndVariants(): void
    {
        // Arrange - Create a configurable product with UUID-format IDs
        $configurableProductId = ConfigurableProductId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString());
        $productId = ProductId::generate();

        $configurableProduct = ConfigurableProduct::create(
            $configurableProductId,
            $productId,
            $this->tenantId
        );

        // Define options (color and size) with UUID-format IDs
        $colorOption = Option::create(
            OptionId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color', 'fr' => 'Couleur']),
            1,
            []
        );

        $sizeOption = Option::create(
            OptionId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size', 'fr' => 'Taille']),
            2,
            []
        );

        $configurableProduct->defineOption($colorOption);
        $configurableProduct->defineOption($sizeOption);

        // Add variants with UUID-format IDs
        $variant1 = Variant::create(
            VariantId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            VariantSKU::fromString('SKU-TST-000001-red-small'),
            ['color' => 'red', 'size' => 'small'],
            Money::fromScalars(1999, 'USD'),
            Stock::create(10, true, false),
            true
        );

        $variant2 = Variant::create(
            VariantId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            VariantSKU::fromString('SKU-TST-000002-blue-large'),
            ['color' => 'blue', 'size' => 'large'],
            Money::fromScalars(2499, 'USD'),
            Stock::create(5, true, false),
            true
        );

        $configurableProduct->addVariant($variant1);
        $configurableProduct->addVariant($variant2);

        // Act - Save the configurable product
        $this->repository->save($configurableProduct);

        // Retrieve it back
        $found = $this->repository->findById($configurableProductId, $this->tenantId);

        // Assert
        $this->assertNotNull($found, 'ConfigurableProduct should be found');
        $this->assertTrue($found->getId()->equals($configurableProductId), 'IDs should match');
        $this->assertTrue($found->getProductId()->equals($productId), 'Product IDs should match');
        $this->assertTrue($found->getTenantId()->equals($this->tenantId), 'Tenant IDs should match');

        // Verify options
        $options = $found->getOptions();
        $this->assertCount(2, $options, 'Should have 2 options');

        $sortedOptions = $found->getSortedOptions();
        $this->assertEquals('color', $sortedOptions[0]->getCode()->toString(), 'First option should be color');
        $this->assertEquals('size', $sortedOptions[1]->getCode()->toString(), 'Second option should be size');

        // Verify option translations
        $colorTranslations = $sortedOptions[0]->getNameTranslations()->toArray();
        $this->assertEquals('Color', $colorTranslations['en'], 'English color translation');
        $this->assertEquals('Couleur', $colorTranslations['fr'], 'French color translation');

        // Verify variants
        $variants = $found->getVariants();
        $this->assertCount(2, $variants, 'Should have 2 variants');

        // Verify variant details
        $variant1Retrieved = $variants[0];
        $this->assertEquals('SKU-TST-000001-red-small', $variant1Retrieved->getSku()->toString());
        $this->assertEquals(['color' => 'red', 'size' => 'small'], $variant1Retrieved->getOptionValueMap());
        $this->assertEquals(1999, $variant1Retrieved->getPrice()->getAmount());
        $this->assertEquals('USD', $variant1Retrieved->getPrice()->getCurrency()->getCurrencyCode());
        $this->assertEquals(10, $variant1Retrieved->getStock()->quantity());
        $this->assertTrue($variant1Retrieved->isActive());

        $variant2Retrieved = $variants[1];
        $this->assertEquals('SKU-TST-000002-blue-large', $variant2Retrieved->getSku()->toString());
        $this->assertEquals(['color' => 'blue', 'size' => 'large'], $variant2Retrieved->getOptionValueMap());
        $this->assertEquals(2499, $variant2Retrieved->getPrice()->getAmount());
    }

    /**
     * Test that we can find a configurable product by its product ID.
     */
    public function testFindByProductId(): void
    {
        // Arrange - Create a configurable product with UUID-format IDs
        $configurableProductId = ConfigurableProductId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString());
        $productId = ProductId::generate();

        $configurableProduct = ConfigurableProduct::create(
            $configurableProductId,
            $productId,
            $this->tenantId
        );

        // Define at least one option with UUID-format ID
        $colorOption = Option::create(
            OptionId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString()),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            1,
            []
        );

        $configurableProduct->defineOption($colorOption);

        // Save the configurable product
        $this->repository->save($configurableProduct);

        // Act - Find by product ID
        $found = $this->repository->findByProductId($productId, $this->tenantId);

        // Assert
        $this->assertNotNull($found, 'ConfigurableProduct should be found by product ID');
        $this->assertTrue($found->getId()->equals($configurableProductId), 'ConfigurableProduct ID should match');
        $this->assertTrue($found->getProductId()->equals($productId), 'Product ID should match');
        $this->assertTrue($found->getTenantId()->equals($this->tenantId), 'Tenant ID should match');
        $this->assertCount(1, $found->getOptions(), 'Should have 1 option');
    }
}
