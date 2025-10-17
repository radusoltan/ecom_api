<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Product fixtures - creates products with translations and images
 */
class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    private int $imageCounter = 100;
    private int $productCounter = 1;

    public function load(ObjectManager $manager): void
    {
        echo "🛍️  Creating products with translations and images...\n";

        // Get first tenant ID from database
        $tenantId = $this->getFirstTenantId($manager);

        // Set tenant context for RLS
        $connection = $manager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        // Get all category IDs mapped by name
        $categories = $this->getAllCategories($manager);

        $productsCreated = 0;

        // Create products for each category
        foreach ($categories as $category) {
            $productCount = 12; // 12 products per category

            for ($i = 1; $i <= $productCount; $i++) {
                $this->createProduct(
                    $manager,
                    $tenantId,
                    $category['id'],
                    $category['name'],
                    $i
                );
                $productsCreated++;
            }
        }

        $manager->flush();
        echo "✅ Created {$productsCreated} products with translations and images\n";
    }

    private function createProduct(
        ObjectManager $manager,
        string $tenantId,
        string $categoryId,
        string $categoryName,
        int $index
    ): void {
        $productId = Uuid::v4()->toString();
        // Generate SKU in format AAA-BBB-000000 (e.g., PRD-CAT-000001)
        $categoryCode = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $categoryName), 0, 3));
        if (strlen($categoryCode) < 3) {
            $categoryCode = str_pad($categoryCode, 3, 'X');
        }
        $sku = sprintf('PRD-%s-%06d', $categoryCode, $this->productCounter++);

        // Random price between $10 and $500
        $price = rand(1000, 50000);

        // Random stock between 10 and 200
        $stock = rand(10, 200);

        // Generate random number of images (1-5)
        $numImages = rand(1, 5);
        $images = [];
        for ($i = 0; $i < $numImages; $i++) {
            $images[] = [
                'url' => "https://picsum.photos/800/600?random=" . $this->imageCounter++,
                'position' => $i + 1,
                'isPrimary' => $i === 0
            ];
        }

        $baseName = ucfirst($categoryName) . " Product {$index}";

        $product = new ProductEntity();
        $product->setTenantId($tenantId);
        $product->setSku($sku);
        $product->setPriceAmount($price);
        $product->setPriceCurrency('USD');
        $product->setCategoryId($categoryId);
        $product->setStockQuantity($stock);
        $product->setTrackInventory(true);
        $product->setAllowBackorder(false);
        $product->setImages($images);
        $product->setActive(true);
        $product->setIsFeatured($index <= 3); // First 3 are featured

        // Use reflection to set private properties
        $reflection = new \ReflectionClass($product);

        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($product, $productId);

        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setValue($product, new DateTimeImmutable());

        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setValue($product, new DateTimeImmutable());

        // Set English content only (translations can be added via admin panel later)
        $product->setTranslatableLocale('en');
        $product->setName($baseName);
        $product->setShortDescription("High-quality {$baseName} with excellent features");
        $product->setDescription("This premium {$baseName} offers exceptional quality and performance. Perfect for everyday use with modern design and reliable functionality.");

        $manager->persist($product);
        // Note: Slug will be generated on flush
    }

    private function getFirstTenantId(ObjectManager $manager): string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1')->fetchOne();

        return $result;
    }

    private function getAllCategories(ObjectManager $manager): array
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id, name FROM catalog_categories WHERE parent_id IS NOT NULL ORDER BY created_at ASC')->fetchAllAssociative();

        return $result;
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
            CategoryFixtures::class,
        ];
    }

    public function getOrder(): int
    {
        return 4;
    }
}
