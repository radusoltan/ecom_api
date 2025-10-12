<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class C_AppFixtures extends Fixture
{
    private string $tenantId;
    private array $categoryIds = [];
    private array $productIds = [];

    public function load(ObjectManager $manager): void
    {
        // Get first tenant ID from database
        $this->tenantId = $this->getFirstTenantId($manager);

        if (!$this->tenantId) {
            echo "⚠️  No tenants found in database. Please create a tenant first.\n";
            return;
        }

        echo "📦 Loading fixtures for tenant: {$this->tenantId}\n";

        // Load categories with translations
        $this->loadCategories($manager);

        // Load products with translations
        $this->loadProducts($manager);

        // Load orders
        $this->loadOrders($manager);

        $manager->flush();

        echo "✅ Fixtures loaded successfully!\n";
        echo "   - Categories: " . count($this->categoryIds) . "\n";
        echo "   - Products: " . count($this->productIds) . "\n";
        echo "   - Orders: 10\n";
    }

    public function getOrder(): int
    {
        // Run after TenantFixtures (1) and CustomerFixtures (2)
        return 3;
    }

    private function getFirstTenantId(ObjectManager $manager): ?string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants LIMIT 1')->fetchOne();

        return $result ?: null;
    }

    private function loadCategories(ObjectManager $manager): void
    {
        echo "📂 Creating categories with translations (EN, FR, DE)...\n";

        $categories = [
            [
                'name' => ['en' => 'Electronics', 'fr' => 'Électronique', 'de' => 'Elektronik'],
                'description' => [
                    'en' => 'Electronic devices and accessories',
                    'fr' => 'Appareils électroniques et accessoires',
                    'de' => 'Elektronische Geräte und Zubehör'
                ],
            ],
            [
                'name' => ['en' => 'Clothing', 'fr' => 'Vêtements', 'de' => 'Kleidung'],
                'description' => [
                    'en' => 'Fashion and apparel for all seasons',
                    'fr' => 'Mode et vêtements pour toutes les saisons',
                    'de' => 'Mode und Bekleidung für alle Jahreszeiten'
                ],
            ],
            [
                'name' => ['en' => 'Home & Garden', 'fr' => 'Maison & Jardin', 'de' => 'Haus & Garten'],
                'description' => [
                    'en' => 'Furniture, decor, and garden supplies',
                    'fr' => 'Meubles, décoration et fournitures de jardin',
                    'de' => 'Möbel, Dekoration und Gartenbedarf'
                ],
            ],
            [
                'name' => ['en' => 'Sports & Outdoors', 'fr' => 'Sports & Plein Air', 'de' => 'Sport & Outdoor'],
                'description' => [
                    'en' => 'Sporting goods and outdoor equipment',
                    'fr' => 'Articles de sport et équipement de plein air',
                    'de' => 'Sportartikel und Outdoor-Ausrüstung'
                ],
            ],
            [
                'name' => ['en' => 'Books & Media', 'fr' => 'Livres & Médias', 'de' => 'Bücher & Medien'],
                'description' => [
                    'en' => 'Books, music, movies, and games',
                    'fr' => 'Livres, musique, films et jeux',
                    'de' => 'Bücher, Musik, Filme und Spiele'
                ],
            ],
        ];

        $position = 1;
        foreach ($categories as $categoryData) {
            $categoryId = Uuid::v4()->toString();
            $this->categoryIds[] = $categoryId;

            // Create category with English (default locale)
            $category = new CategoryEntity();
            $category->setTenantId($this->tenantId);
            $category->setPosition($position++);
            $category->setActive(true);

            // Use reflection to set private properties for initial creation
            $reflection = new \ReflectionClass($category);

            $idProperty = $reflection->getProperty('id');
            $idProperty->setValue($category, $categoryId);

            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setValue($category, new DateTimeImmutable());

            $updatedAtProperty = $reflection->getProperty('updatedAt');
            $updatedAtProperty->setValue($category, new DateTimeImmutable());

            // Set English content (default)
            $category->setTranslatableLocale('en');
            $category->setName($categoryData['name']['en']);
            $category->setDescription($categoryData['description']['en']);

            $manager->persist($category);
            $manager->flush(); // Flush to generate slug

            // Add French translation
            $category->setTranslatableLocale('fr');
            $category->setName($categoryData['name']['fr']);
            $category->setDescription($categoryData['description']['fr']);

            $manager->persist($category);
            $manager->flush();

            // Add German translation
            $category->setTranslatableLocale('de');
            $category->setName($categoryData['name']['de']);
            $category->setDescription($categoryData['description']['de']);

            $manager->persist($category);
            $manager->flush();

            // Reset to default locale
            $category->setTranslatableLocale('en');
            $manager->persist($category);
        }

        $manager->flush();
        echo "   ✓ Created " . count($categories) . " categories with translations\n";
    }

    private function loadProducts(ObjectManager $manager): void
    {
        echo "🛍️  Creating products with translations (EN, FR, DE)...\n";

        $products = [
            // Electronics
            [
                'sku' => 'ELC-001-LAP',
                'name' => ['en' => 'Laptop Dell XPS 15', 'fr' => 'Ordinateur portable Dell XPS 15', 'de' => 'Laptop Dell XPS 15'],
                'shortDescription' => [
                    'en' => 'Powerful laptop for professionals',
                    'fr' => 'Ordinateur portable puissant pour professionnels',
                    'de' => 'Leistungsstarker Laptop für Profis'
                ],
                'description' => [
                    'en' => '15.6" 4K display, Intel Core i7, 16GB RAM, 512GB SSD. Perfect for creative work and gaming.',
                    'fr' => 'Écran 15,6" 4K, Intel Core i7, 16 Go RAM, SSD 512 Go. Parfait pour le travail créatif et les jeux.',
                    'de' => '15,6" 4K-Display, Intel Core i7, 16 GB RAM, 512 GB SSD. Perfekt für kreative Arbeit und Gaming.'
                ],
                'price' => 129999,
                'categoryIndex' => 0,
                'stock' => 25,
                'images' => [
                    ['url' => 'https://picsum.photos/800/600?random=1', 'position' => 1, 'isPrimary' => true],
                    ['url' => 'https://picsum.photos/800/600?random=2', 'position' => 2, 'isPrimary' => false],
                ],
            ],
            [
                'sku' => 'ELC-002-PHO',
                'name' => ['en' => 'Smartphone Pro Max', 'fr' => 'Smartphone Pro Max', 'de' => 'Smartphone Pro Max'],
                'shortDescription' => [
                    'en' => 'Latest flagship smartphone',
                    'fr' => 'Dernier smartphone phare',
                    'de' => 'Neuestes Flaggschiff-Smartphone'
                ],
                'description' => [
                    'en' => '6.7" OLED display, 256GB storage, 5G enabled, triple camera system with AI enhancement.',
                    'fr' => 'Écran OLED 6,7", stockage 256 Go, compatible 5G, triple caméra avec amélioration IA.',
                    'de' => '6,7" OLED-Display, 256 GB Speicher, 5G-fähig, Dreifach-Kamera mit KI-Verbesserung.'
                ],
                'price' => 99999,
                'categoryIndex' => 0,
                'stock' => 50,
                'images' => [
                    ['url' => 'https://picsum.photos/800/600?random=3', 'position' => 1, 'isPrimary' => true],
                ],
            ],
            // Clothing
            [
                'sku' => 'CLO-001-TSH',
                'name' => ['en' => 'Cotton T-Shirt Premium', 'fr' => 'T-Shirt en Coton Premium', 'de' => 'Baumwoll-T-Shirt Premium'],
                'shortDescription' => [
                    'en' => '100% organic cotton, comfortable fit',
                    'fr' => '100% coton biologique, coupe confortable',
                    'de' => '100% Bio-Baumwolle, bequeme Passform'
                ],
                'description' => [
                    'en' => 'Premium quality t-shirt made from 100% organic cotton. Available in multiple colors and sizes.',
                    'fr' => 'T-shirt de qualité premium en 100% coton biologique. Disponible en plusieurs couleurs et tailles.',
                    'de' => 'Premium-T-Shirt aus 100% Bio-Baumwolle. Erhältlich in mehreren Farben und Größen.'
                ],
                'price' => 2999,
                'categoryIndex' => 1,
                'stock' => 150,
                'images' => [
                    ['url' => 'https://picsum.photos/800/600?random=4', 'position' => 1, 'isPrimary' => true],
                ],
            ],
            [
                'sku' => 'CLO-002-JEA',
                'name' => ['en' => 'Denim Jeans Classic', 'fr' => 'Jean Denim Classique', 'de' => 'Denim Jeans Klassisch'],
                'shortDescription' => [
                    'en' => 'Classic fit denim jeans',
                    'fr' => 'Jean denim coupe classique',
                    'de' => 'Klassische Denim-Jeans'
                ],
                'description' => [
                    'en' => 'Timeless denim jeans with classic fit. Durable and stylish for everyday wear.',
                    'fr' => 'Jean denim intemporel avec coupe classique. Durable et élégant pour un usage quotidien.',
                    'de' => 'Zeitlose Denim-Jeans mit klassischer Passform. Strapazierfähig und stylisch für den Alltag.'
                ],
                'price' => 5999,
                'categoryIndex' => 1,
                'stock' => 80,
                'images' => [
                    ['url' => 'https://picsum.photos/800/600?random=5', 'position' => 1, 'isPrimary' => true],
                ],
            ],
            // Home & Garden
            [
                'sku' => 'HOM-001-CHA',
                'name' => ['en' => 'Ergonomic Office Chair', 'fr' => 'Chaise de Bureau Ergonomique', 'de' => 'Ergonomischer Bürostuhl'],
                'shortDescription' => [
                    'en' => 'Comfortable chair for long work sessions',
                    'fr' => 'Chaise confortable pour de longues sessions de travail',
                    'de' => 'Bequemer Stuhl für lange Arbeitssitzungen'
                ],
                'description' => [
                    'en' => 'Premium ergonomic office chair with lumbar support, adjustable height and armrests. Perfect for home office.',
                    'fr' => 'Chaise de bureau ergonomique premium avec support lombaire, hauteur et accoudoirs réglables. Parfaite pour le bureau à domicile.',
                    'de' => 'Premium ergonomischer Bürostuhl mit Lendenwirbelstütze, verstellbarer Höhe und Armlehnen. Perfekt fürs Home Office.'
                ],
                'price' => 34999,
                'categoryIndex' => 2,
                'stock' => 30,
                'images' => [
                    ['url' => 'https://picsum.photos/800/600?random=6', 'position' => 1, 'isPrimary' => true],
                ],
            ],
            // Sports
            [
                'sku' => 'SPO-001-YOG',
                'name' => ['en' => 'Yoga Mat Pro', 'fr' => 'Tapis de Yoga Pro', 'de' => 'Yoga-Matte Pro'],
                'shortDescription' => [
                    'en' => 'Non-slip yoga mat for all levels',
                    'fr' => 'Tapis de yoga antidérapant pour tous niveaux',
                    'de' => 'Rutschfeste Yoga-Matte für alle Levels'
                ],
                'description' => [
                    'en' => 'Professional-grade yoga mat with excellent grip and cushioning. 6mm thick, eco-friendly materials.',
                    'fr' => 'Tapis de yoga professionnel avec excellente adhérence et amorti. 6 mm d\'épaisseur, matériaux écologiques.',
                    'de' => 'Professionelle Yoga-Matte mit ausgezeichnetem Grip und Dämpfung. 6 mm dick, umweltfreundliche Materialien.'
                ],
                'price' => 3999,
                'categoryIndex' => 3,
                'stock' => 100,
                'images' => [
                    ['url' => 'https://picsum.photos/800/600?random=7', 'position' => 1, 'isPrimary' => true],
                ],
            ],
            // Books
            [
                'sku' => 'BOO-001-NOV',
                'name' => ['en' => 'The Great Novel', 'fr' => 'Le Grand Roman', 'de' => 'Der Große Roman'],
                'shortDescription' => [
                    'en' => 'Bestselling fiction novel',
                    'fr' => 'Roman de fiction best-seller',
                    'de' => 'Bestseller-Roman'
                ],
                'description' => [
                    'en' => 'Award-winning novel that captivates readers worldwide. A must-read for fiction lovers.',
                    'fr' => 'Roman primé qui captive les lecteurs du monde entier. À lire absolument pour les amateurs de fiction.',
                    'de' => 'Preisgekrönter Roman, der Leser weltweit fesselt. Ein Muss für Fiction-Liebhaber.'
                ],
                'price' => 1999,
                'categoryIndex' => 4,
                'stock' => 200,
                'images' => [
                    ['url' => 'https://picsum.photos/800/600?random=8', 'position' => 1, 'isPrimary' => true],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $productId = Uuid::v4()->toString();
            $this->productIds[] = $productId;

            // Create product with English (default locale)
            $product = new ProductEntity();
            $product->setTenantId($this->tenantId);
            $product->setSku($productData['sku']);
            $product->setPriceAmount($productData['price']);
            $product->setPriceCurrency('USD');
            $product->setCategoryId($this->categoryIds[$productData['categoryIndex']]);
            $product->setStockQuantity($productData['stock']);
            $product->setTrackInventory(true);
            $product->setAllowBackorder(false);
            $product->setImages($productData['images']);
            $product->setActive(true);

            // Use reflection to set private properties
            $reflection = new \ReflectionClass($product);

            $idProperty = $reflection->getProperty('id');
            $idProperty->setValue($product, $productId);

            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setValue($product, new DateTimeImmutable());

            $updatedAtProperty = $reflection->getProperty('updatedAt');
            $updatedAtProperty->setValue($product, new DateTimeImmutable());

            // Set English content (default)
            $product->setTranslatableLocale('en');
            $product->setName($productData['name']['en']);
            $product->setShortDescription($productData['shortDescription']['en']);
            $product->setDescription($productData['description']['en']);

            $manager->persist($product);
            $manager->flush(); // Flush to generate slug

            // Add French translation
            $product->setTranslatableLocale('fr');
            $product->setName($productData['name']['fr']);
            $product->setShortDescription($productData['shortDescription']['fr']);
            $product->setDescription($productData['description']['fr']);

            $manager->persist($product);
            $manager->flush();

            // Add German translation
            $product->setTranslatableLocale('de');
            $product->setName($productData['name']['de']);
            $product->setShortDescription($productData['shortDescription']['de']);
            $product->setDescription($productData['description']['de']);

            $manager->persist($product);
            $manager->flush();

            // Reset to default locale
            $product->setTranslatableLocale('en');
            $manager->persist($product);
        }

        $manager->flush();
        echo "   ✓ Created " . count($products) . " products with translations\n";
    }

    private function loadOrders(ObjectManager $manager): void
    {
        echo "📦 Creating orders...\n";

        $statuses = ['pending', 'processing', 'shipped', 'delivered'];
        $customerEmails = [
            'john.doe@example.com',
            'jane.smith@example.com',
            'bob.wilson@example.com',
            'alice.johnson@example.com',
        ];

        $shippingAddress = [
            'street' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'postalCode' => '10001',
            'country' => 'US',
        ];

        $billingAddress = [
            'street' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'postalCode' => '10001',
            'country' => 'US',
        ];

        for ($i = 0; $i < 10; $i++) {
            $orderId = Uuid::v4()->toString();
            $customerEmail = $customerEmails[$i % count($customerEmails)];
            $status = $statuses[$i % count($statuses)];

            // Create 1-3 random order lines
            $numLines = rand(1, 3);
            $lines = [];

            for ($j = 0; $j < $numLines; $j++) {
                $productIndex = rand(0, count($this->productIds) - 1);
                $quantity = rand(1, 3);
                $unitPrice = rand(1000, 50000);

                $lines[] = [
                    'productId' => $this->productIds[$productIndex],
                    'productName' => 'Product ' . ($productIndex + 1),
                    'quantity' => $quantity,
                    'unitPriceAmount' => $unitPrice,
                    'unitPriceCurrency' => 'USD',
                ];
            }

            // Use reflection to create order with private properties
            $order = new OrderEntity();
            $reflection = new \ReflectionClass($order);

            $idProperty = $reflection->getProperty('id');
            $idProperty->setValue($order, $orderId);

            $tenantIdProperty = $reflection->getProperty('tenantId');
            $tenantIdProperty->setValue($order, $this->tenantId);

            $customerEmailProperty = $reflection->getProperty('customerEmail');
            $customerEmailProperty->setValue($order, $customerEmail);

            $statusProperty = $reflection->getProperty('status');
            $statusProperty->setValue($order, $status);

            $linesProperty = $reflection->getProperty('lines');
            $linesProperty->setValue($order, $lines);

            $shippingAddressProperty = $reflection->getProperty('shippingAddress');
            $shippingAddressProperty->setValue($order, $shippingAddress);

            $billingAddressProperty = $reflection->getProperty('billingAddress');
            $billingAddressProperty->setValue($order, $billingAddress);

            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setValue($order, new DateTimeImmutable("-{$i} days"));

            $updatedAtProperty = $reflection->getProperty('updatedAt');
            $updatedAtProperty->setValue($order, new DateTimeImmutable("-{$i} days"));

            $manager->persist($order);
        }

        $manager->flush();
        echo "   ✓ Created 10 orders with various statuses\n";
    }
}
