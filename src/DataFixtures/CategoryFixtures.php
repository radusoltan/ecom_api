<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Category fixtures - creates hierarchical categories with translations
 * 3 root categories, each with 3 subcategories (12 total).
 */
class CategoryFixtures extends Fixture implements DependentFixtureInterface
{
    // Reference constants for ProductFixtures
    public const CATEGORY_ELECTRONICS = 'category_electronics';
    public const CATEGORY_ELECTRONICS_LAPTOPS = 'category_electronics_laptops';
    public const CATEGORY_ELECTRONICS_SMARTPHONES = 'category_electronics_smartphones';
    public const CATEGORY_ELECTRONICS_ACCESSORIES = 'category_electronics_accessories';

    public const CATEGORY_FASHION = 'category_fashion';
    public const CATEGORY_FASHION_MENS = 'category_fashion_mens';
    public const CATEGORY_FASHION_WOMENS = 'category_fashion_womens';
    public const CATEGORY_FASHION_KIDS = 'category_fashion_kids';

    public const CATEGORY_HOME = 'category_home';
    public const CATEGORY_HOME_FURNITURE = 'category_home_furniture';
    public const CATEGORY_HOME_DECOR = 'category_home_decor';
    public const CATEGORY_HOME_KITCHEN = 'category_home_kitchen';

    public function load(ObjectManager $manager): void
    {
        echo "📂 Creating categories with translations...\n";

        // Get first tenant ID from database
        $tenantId = $this->getFirstTenantId($manager);

        // Set tenant context for RLS
        $connection = $manager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        // Root Category 1: Electronics
        $electronicsId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Electronics',
                'fr' => 'Électronique',
                'de' => 'Elektronik',
            ],
            [
                'en' => 'Latest electronic devices and gadgets',
                'fr' => 'Derniers appareils électroniques et gadgets',
                'de' => 'Neueste elektronische Geräte und Gadgets',
            ],
            null,
            1,
            'https://picsum.photos/1200/400?random=1'
        );
        echo "   ✓ Electronics category created\n";

        // Electronics Subcategories
        $laptopsId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Laptops & Computers',
                'fr' => 'Ordinateurs portables',
                'de' => 'Laptops & Computer',
            ],
            [
                'en' => 'High-performance laptops and desktop computers',
                'fr' => 'Ordinateurs portables et de bureau haute performance',
                'de' => 'Hochleistungs-Laptops und Desktop-Computer',
            ],
            $electronicsId,
            1,
            'https://picsum.photos/1200/400?random=2'
        );

        $smartphonesId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Smartphones & Tablets',
                'fr' => 'Smartphones et tablettes',
                'de' => 'Smartphones & Tablets',
            ],
            [
                'en' => 'Latest smartphones and tablets',
                'fr' => 'Derniers smartphones et tablettes',
                'de' => 'Neueste Smartphones und Tablets',
            ],
            $electronicsId,
            2,
            'https://picsum.photos/1200/400?random=3'
        );

        $accessoriesId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Electronics Accessories',
                'fr' => 'Accessoires électroniques',
                'de' => 'Elektronik-Zubehör',
            ],
            [
                'en' => 'Chargers, cases, and more',
                'fr' => 'Chargeurs, étuis et plus',
                'de' => 'Ladegeräte, Hüllen und mehr',
            ],
            $electronicsId,
            3,
            'https://picsum.photos/1200/400?random=4'
        );
        echo "   ✓ Electronics subcategories created (3)\n";

        // Root Category 2: Fashion
        $fashionId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Fashion & Apparel',
                'fr' => 'Mode et vêtements',
                'de' => 'Mode & Bekleidung',
            ],
            [
                'en' => 'Stylish clothing for everyone',
                'fr' => 'Vêtements élégants pour tous',
                'de' => 'Stilvolle Kleidung für alle',
            ],
            null,
            2,
            'https://picsum.photos/1200/400?random=5'
        );
        echo "   ✓ Fashion category created\n";

        // Fashion Subcategories
        $mensId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => "Men's Clothing",
                'fr' => 'Vêtements pour hommes',
                'de' => 'Herrenbekleidung',
            ],
            [
                'en' => 'Stylish clothing and accessories for men',
                'fr' => 'Vêtements et accessoires élégants pour hommes',
                'de' => 'Stilvolle Kleidung und Accessoires für Herren',
            ],
            $fashionId,
            1,
            'https://picsum.photos/1200/400?random=6'
        );

        $womensId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => "Women's Clothing",
                'fr' => 'Vêtements pour femmes',
                'de' => 'Damenbekleidung',
            ],
            [
                'en' => 'Fashion-forward clothing and accessories for women',
                'fr' => 'Vêtements et accessoires tendance pour femmes',
                'de' => 'Modische Kleidung und Accessoires für Damen',
            ],
            $fashionId,
            2,
            'https://picsum.photos/1200/400?random=7'
        );

        $kidsId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => "Kids' Clothing",
                'fr' => 'Vêtements pour enfants',
                'de' => 'Kinderbekleidung',
            ],
            [
                'en' => 'Comfortable and fun clothing for children',
                'fr' => 'Vêtements confortables et amusants pour enfants',
                'de' => 'Bequeme und lustige Kleidung für Kinder',
            ],
            $fashionId,
            3,
            'https://picsum.photos/1200/400?random=8'
        );
        echo "   ✓ Fashion subcategories created (3)\n";

        // Root Category 3: Home & Living
        $homeId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Home & Living',
                'fr' => 'Maison et vie',
                'de' => 'Haus & Wohnen',
            ],
            [
                'en' => 'Everything for your home',
                'fr' => 'Tout pour votre maison',
                'de' => 'Alles für Ihr Zuhause',
            ],
            null,
            3,
            'https://picsum.photos/1200/400?random=9'
        );
        echo "   ✓ Home & Living category created\n";

        // Home Subcategories
        $furnitureId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Furniture',
                'fr' => 'Meubles',
                'de' => 'Möbel',
            ],
            [
                'en' => 'Quality furniture for every room',
                'fr' => 'Meubles de qualité pour chaque pièce',
                'de' => 'Hochwertige Möbel für jeden Raum',
            ],
            $homeId,
            1,
            'https://picsum.photos/1200/400?random=10'
        );

        $decorId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Home Decor',
                'fr' => 'Décoration intérieure',
                'de' => 'Wohndekoration',
            ],
            [
                'en' => 'Beautiful decorations for your home',
                'fr' => 'Belles décorations pour votre maison',
                'de' => 'Schöne Dekorationen für Ihr Zuhause',
            ],
            $homeId,
            2,
            'https://picsum.photos/1200/400?random=11'
        );

        $kitchenId = $this->createCategory(
            $manager,
            $tenantId,
            [
                'en' => 'Kitchen & Dining',
                'fr' => 'Cuisine et salle à manger',
                'de' => 'Küche & Esszimmer',
            ],
            [
                'en' => 'Cookware, utensils, and dining essentials',
                'fr' => 'Ustensiles de cuisine et essentiels pour la salle à manger',
                'de' => 'Kochgeschirr, Utensilien und Essentials fürs Esszimmer',
            ],
            $homeId,
            3,
            'https://picsum.photos/1200/400?random=12'
        );
        echo "   ✓ Home & Living subcategories created (3)\n";

        $manager->flush();
        echo "✅ All categories created (3 root + 9 subcategories = 12 total)\n";
    }

    private function createCategory(
        ObjectManager $manager,
        string $tenantId,
        array $names,
        array $descriptions,
        ?string $parentId,
        int $position,
        string $coverImage
    ): string {
        $categoryId = Uuid::v4()->toString();

        // Create category with English (default locale)
        $category = new CategoryEntity();
        $category->setTenantId($tenantId);
        $category->setPosition($position);
        $category->setActive(true);
        $category->setShowOnFront(true);
        $category->setCoverImage($coverImage);

        if (null !== $parentId) {
            $category->setParentId($parentId);
        }

        // Use reflection to set private properties
        $reflection = new \ReflectionClass($category);

        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($category, $categoryId);

        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setValue($category, new \DateTimeImmutable());

        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setValue($category, new \DateTimeImmutable());

        // Set English content only (translations can be added via admin panel later)
        $category->setTranslatableLocale('en');
        $category->setName($names['en']);
        $category->setDescription($descriptions['en']);

        $manager->persist($category);
        $manager->flush(); // Flush immediately to generate slug and commit to DB

        return $categoryId;
    }

    private function getFirstTenantId(ObjectManager $manager): string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1')->fetchOne();

        return $result;
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
        ];
    }

    public function getOrder(): int
    {
        return 3;
    }
}
