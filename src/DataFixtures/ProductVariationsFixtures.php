<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Product Variations Fixtures - Creates configurable products with options and variants.
 *
 * Adds Color and Size options to selected products and generates variants
 */
class ProductVariationsFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['variations', 'product-variations'];
    }
    // Define common options
    private const COLORS = [
        'red' => ['en' => 'Red', 'fr' => 'Rouge', 'de' => 'Rot'],
        'blue' => ['en' => 'Blue', 'fr' => 'Bleu', 'de' => 'Blau'],
        'black' => ['en' => 'Black', 'fr' => 'Noir', 'de' => 'Schwarz'],
        'white' => ['en' => 'White', 'fr' => 'Blanc', 'de' => 'Weiß'],
        'green' => ['en' => 'Green', 'fr' => 'Vert', 'de' => 'Grün'],
    ];

    private const SIZES = [
        'xs' => ['en' => 'Extra Small', 'fr' => 'Très Petit', 'de' => 'Extra Klein'],
        's' => ['en' => 'Small', 'fr' => 'Petit', 'de' => 'Klein'],
        'm' => ['en' => 'Medium', 'fr' => 'Moyen', 'de' => 'Mittel'],
        'l' => ['en' => 'Large', 'fr' => 'Grand', 'de' => 'Groß'],
        'xl' => ['en' => 'Extra Large', 'fr' => 'Très Grand', 'de' => 'Extra Groß'],
    ];

    public function load(ObjectManager $manager): void
    {
        echo "🎨 Creating product variations (options and variants)...\n";

        // Get first tenant ID
        $tenantId = $this->getFirstTenantId($manager);

        // Set tenant context for RLS
        $connection = $manager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        // Get some existing products (first 5 from each of first 3 categories)
        $products = $this->getProductsForVariations($manager, 15);

        if (empty($products)) {
            echo "⚠️  No products found. Please run ProductFixtures first.\n";

            return;
        }

        $configurableProductsCreated = 0;
        $variantsCreated = 0;

        foreach ($products as $product) {
            // Create configurable product
            $configurableProductId = Uuid::v4()->toString();
            $this->createConfigurableProduct($manager, $configurableProductId, $product['id'], $tenantId);

            // Add Color and Size options
            $colorOptionId = $this->addColorOption($manager, $configurableProductId);
            $sizeOptionId = $this->addSizeOption($manager, $configurableProductId);

            // Generate variants (all combinations of colors and sizes)
            $variants = $this->generateVariants(
                $manager,
                $configurableProductId,
                $product,
                $colorOptionId,
                $sizeOptionId
            );

            $variantsCreated += count($variants);
            ++$configurableProductsCreated;
        }

        $manager->flush();
        echo "✅ Created {$configurableProductsCreated} configurable products with {$variantsCreated} variants\n";
    }

    private function createConfigurableProduct(
        ObjectManager $manager,
        string $configurableProductId,
        string $productId,
        string $tenantId
    ): void {
        $sql = 'INSERT INTO catalog_configurable_products (id, product_id, tenant_id, created_at, updated_at)
                VALUES (:id, :product_id, :tenant_id, NOW(), NOW())';

        $manager->getConnection()->executeStatement($sql, [
            'id' => $configurableProductId,
            'product_id' => $productId,
            'tenant_id' => $tenantId,
        ]);
    }

    private function addColorOption(ObjectManager $manager, string $configurableProductId): string
    {
        $optionId = Uuid::v4()->toString();

        // Create option
        $sql = 'INSERT INTO catalog_product_options (id, configurable_product_id, code, name_translations, position, created_at)
                VALUES (:id, :configurable_product_id, :code, :name_translations, :position, NOW())';

        $manager->getConnection()->executeStatement($sql, [
            'id' => $optionId,
            'configurable_product_id' => $configurableProductId,
            'code' => 'color',
            'name_translations' => json_encode([
                'en' => 'Color',
                'fr' => 'Couleur',
                'de' => 'Farbe',
            ]),
            'position' => 1,
        ]);

        // Create option values
        $position = 1;
        foreach (self::COLORS as $code => $translations) {
            $valueId = Uuid::v4()->toString();
            $sql = 'INSERT INTO catalog_product_option_values (id, option_id, code, name_translations, position, created_at)
                    VALUES (:id, :option_id, :code, :name_translations, :position, NOW())';

            $manager->getConnection()->executeStatement($sql, [
                'id' => $valueId,
                'option_id' => $optionId,
                'code' => $code,
                'name_translations' => json_encode($translations),
                'position' => $position++,
            ]);
        }

        return $optionId;
    }

    private function addSizeOption(ObjectManager $manager, string $configurableProductId): string
    {
        $optionId = Uuid::v4()->toString();

        // Create option
        $sql = 'INSERT INTO catalog_product_options (id, configurable_product_id, code, name_translations, position, created_at)
                VALUES (:id, :configurable_product_id, :code, :name_translations, :position, NOW())';

        $manager->getConnection()->executeStatement($sql, [
            'id' => $optionId,
            'configurable_product_id' => $configurableProductId,
            'code' => 'size',
            'name_translations' => json_encode([
                'en' => 'Size',
                'fr' => 'Taille',
                'de' => 'Größe',
            ]),
            'position' => 2,
        ]);

        // Create option values
        $position = 1;
        foreach (self::SIZES as $code => $translations) {
            $valueId = Uuid::v4()->toString();
            $sql = 'INSERT INTO catalog_product_option_values (id, option_id, code, name_translations, position, created_at)
                    VALUES (:id, :option_id, :code, :name_translations, :position, NOW())';

            $manager->getConnection()->executeStatement($sql, [
                'id' => $valueId,
                'option_id' => $optionId,
                'code' => $code,
                'name_translations' => json_encode($translations),
                'position' => $position++,
            ]);
        }

        return $optionId;
    }

    private function generateVariants(
        ObjectManager $manager,
        string $configurableProductId,
        array $product,
        string $colorOptionId,
        string $sizeOptionId
    ): array {
        $variants = [];

        // Select subset of combinations (not all - to keep it manageable)
        $selectedColors = array_slice(array_keys(self::COLORS), 0, 3); // First 3 colors
        $selectedSizes = array_slice(array_keys(self::SIZES), 1, 3);   // s, m, l

        $basePrice = (int) $product['price_amount'];
        $baseStock = (int) $product['stock_quantity'];

        $variantIndex = 1;
        foreach ($selectedColors as $colorCode) {
            foreach ($selectedSizes as $sizeCode) {
                $variantId = Uuid::v4()->toString();

                // Generate SKU: base_product_SKU-color-size (lowercase suffix as per VariantSKU rules)
                $sku = $product['sku'].'-'.strtolower($colorCode).'-'.strtolower($sizeCode);

                // Vary price slightly (+/- 10%)
                $priceVariation = rand(-10, 10);
                $variantPrice = $basePrice + (int) ($basePrice * $priceVariation / 100);

                // Vary stock
                $variantStock = rand(5, $baseStock);

                $sql = 'INSERT INTO catalog_product_variants
                        (id, configurable_product_id, sku, option_value_map, price_amount, price_currency,
                         stock_on_hand, stock_reserved, track_inventory, allow_backorder, is_active, images, created_at, updated_at)
                        VALUES (:id, :configurable_product_id, :sku, :option_value_map, :price_amount, :price_currency,
                                :stock_on_hand, :stock_reserved, :track_inventory, :allow_backorder, :is_active, :images, NOW(), NOW())';

                $manager->getConnection()->executeStatement($sql, [
                    'id' => $variantId,
                    'configurable_product_id' => $configurableProductId,
                    'sku' => $sku,
                    'option_value_map' => json_encode([
                        'color' => $colorCode,
                        'size' => $sizeCode,
                    ]),
                    'price_amount' => $variantPrice,
                    'price_currency' => 'USD',
                    'stock_on_hand' => $variantStock,
                    'stock_reserved' => 0,
                    'track_inventory' => 1, // PostgreSQL boolean as integer
                    'allow_backorder' => 0, // PostgreSQL boolean as integer
                    'is_active' => 1, // PostgreSQL boolean as integer
                    'images' => json_encode([]), // Use product images or empty
                ]);

                $variants[] = $variantId;
                ++$variantIndex;
            }
        }

        return $variants;
    }

    private function getFirstTenantId(ObjectManager $manager): string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1')->fetchOne();

        return $result;
    }

    private function getProductsForVariations(ObjectManager $manager, int $limit = 10): array
    {
        $connection = $manager->getConnection();
        $sql = 'SELECT id, sku, price_amount, stock_quantity
                FROM catalog_products
                WHERE active = true
                ORDER BY created_at ASC
                LIMIT :limit';

        return $connection->executeQuery($sql, ['limit' => $limit])->fetchAllAssociative();
    }
}
