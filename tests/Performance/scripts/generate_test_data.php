#!/usr/bin/env php
<?php

/**
 * Test Data Generation Script for Load Testing.
 *
 * Generates realistic test data for performance testing:
 * - Products (100-1000)
 * - Categories (20-50)
 * - Orders (50-200)
 * - Translations (100-500)
 * - Warehouses (3-10)
 *
 * Usage:
 *   php tests/Performance/scripts/generate_test_data.php [size]
 *
 * Sizes:
 *   small  - ~100 products, ~20 categories, ~50 orders
 *   medium - ~500 products, ~30 categories, ~100 orders (default)
 *   large  - ~1000 products, ~50 categories, ~200 orders
 */

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__.'/../../../vendor/autoload.php';

// Load environment
(new Dotenv())->bootEnv(__DIR__.'/../../../.env');

// Database connection
$dbHost = $_ENV['DATABASE_HOST'] ?? '127.0.0.1';
$dbPort = $_ENV['DATABASE_PORT'] ?? '5432';
$dbName = $_ENV['DATABASE_NAME'] ?? 'ecom';
$dbUser = $_ENV['DATABASE_USER'] ?? 'ecom_admin';
$dbPassword = $_ENV['DATABASE_PASSWORD'] ?? 'sr324395';

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo '❌ Database connection failed: '.$e->getMessage()."\n";
    exit(1);
}

// Parse arguments
$size = $argv[1] ?? 'medium';

$config = match ($size) {
    'small' => [
        'products' => 100,
        'categories' => 20,
        'orders' => 50,
        'translations' => 100,
        'warehouses' => 3,
    ],
    'medium' => [
        'products' => 500,
        'categories' => 30,
        'orders' => 100,
        'translations' => 200,
        'warehouses' => 5,
    ],
    'large' => [
        'products' => 1000,
        'categories' => 50,
        'orders' => 200,
        'translations' => 500,
        'warehouses' => 10,
    ],
    default => throw new InvalidArgumentException("Invalid size: $size (use: small, medium, large)"),
};

echo "🚀 Generating test data (size: $size)\n";
echo "   Products: {$config['products']}\n";
echo "   Categories: {$config['categories']}\n";
echo "   Orders: {$config['orders']}\n";
echo "   Translations: {$config['translations']}\n";
echo "   Warehouses: {$config['warehouses']}\n\n";

// Get or create test tenant
$tenantId = getOrCreateTestTenant($pdo);
echo "✓ Using tenant: $tenantId\n";

// Start transaction
$pdo->beginTransaction();

try {
    // 1. Generate Categories
    echo '📦 Generating categories... ';
    $categoryIds = generateCategories($pdo, $tenantId, $config['categories']);
    echo "✓ {$config['categories']} categories created\n";

    // 2. Generate Warehouses
    echo '🏭 Generating warehouses... ';
    generateWarehouses($pdo, $tenantId, $config['warehouses']);
    echo "✓ {$config['warehouses']} warehouses created\n";

    // 3. Generate Products
    echo '🛍️  Generating products... ';
    generateProducts($pdo, $tenantId, $categoryIds, $config['products']);
    echo "✓ {$config['products']} products created\n";

    // 4. Generate Translations
    echo '🌐 Generating translations... ';
    generateTranslations($pdo, $tenantId, $config['translations']);
    echo "✓ {$config['translations']} translations created\n";

    // 5. Generate Orders
    echo '📋 Generating orders... ';
    generateOrders($pdo, $tenantId, $config['orders']);
    echo "✓ {$config['orders']} orders created\n";

    // Commit transaction
    $pdo->commit();

    echo "\n✅ Test data generation complete!\n";
    echo "\n📊 Summary:\n";

    $stats = getStats($pdo, $tenantId);
    foreach ($stats as $table => $count) {
        echo "   $table: $count\n";
    }

    echo "\n💡 Usage:\n";
    echo "   export TENANT_ID='$tenantId'\n";
    echo "   k6 run tests/Performance/k6/smoke_test.js\n";
    echo "   k6 run tests/Performance/k6/api_load_test.js\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo '❌ Error: '.$e->getMessage()."\n";
    exit(1);
}

// Helper Functions

function getOrCreateTestTenant(PDO $pdo): string
{
    // Check for existing test tenant
    $stmt = $pdo->query("SELECT id FROM tenants WHERE name = 'Load Test Tenant' LIMIT 1");
    $result = $stmt->fetch();

    if ($result) {
        return $result['id'];
    }

    // Create new test tenant
    $id = generateUuid();
    $stmt = $pdo->prepare(
        'INSERT INTO tenants (id, name, slug, status, created_at, updated_at)
         VALUES (:id, :name, :slug, :status, NOW(), NOW())'
    );
    $stmt->execute([
        'id' => $id,
        'name' => 'Load Test Tenant',
        'slug' => 'load-test-tenant',
        'status' => 'active',
    ]);

    return $id;
}

function generateCategories(PDO $pdo, string $tenantId, int $count): array
{
    $categoryIds = [];
    $categories = ['Electronics', 'Computers', 'Phones', 'Tablets', 'Accessories', 'Home', 'Fashion', 'Sports', 'Books', 'Toys'];

    $stmt = $pdo->prepare(
        'INSERT INTO categories (id, tenant_id, slug, name_translations, is_active, position, created_at, updated_at)
         VALUES (:id, :tenant_id, :slug, :name_translations, :is_active, :position, NOW(), NOW())'
    );

    for ($i = 0; $i < min($count, count($categories)); ++$i) {
        $id = generateUuid();
        $name = $categories[$i];
        $slug = strtolower(str_replace(' ', '-', $name));

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'slug' => $slug,
            'name_translations' => json_encode(['en_US' => $name]),
            'is_active' => true,
            'position' => $i + 1,
        ]);

        $categoryIds[] = $id;
    }

    return $categoryIds;
}

function generateWarehouses(PDO $pdo, string $tenantId, int $count): void
{
    $warehouses = [
        ['name' => 'Main Warehouse', 'code' => 'WH-MAIN', 'city' => 'New York'],
        ['name' => 'West Coast Hub', 'code' => 'WH-WEST', 'city' => 'Los Angeles'],
        ['name' => 'East Coast Hub', 'code' => 'WH-EAST', 'city' => 'Boston'],
        ['name' => 'Central Warehouse', 'code' => 'WH-CENTRAL', 'city' => 'Chicago'],
        ['name' => 'South Warehouse', 'code' => 'WH-SOUTH', 'city' => 'Houston'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO warehouses (id, tenant_id, name, code, address, is_active, priority, created_at, updated_at)
         VALUES (:id, :tenant_id, :name, :code, :address, :is_active, :priority, NOW(), NOW())'
    );

    for ($i = 0; $i < min($count, count($warehouses)); ++$i) {
        $w = $warehouses[$i];
        $stmt->execute([
            'id' => generateUuid(),
            'tenant_id' => $tenantId,
            'name' => $w['name'],
            'code' => $w['code'],
            'address' => json_encode(['city' => $w['city'], 'country' => 'USA']),
            'is_active' => true,
            'priority' => $count - $i,
        ]);
    }
}

function generateProducts(PDO $pdo, string $tenantId, array $categoryIds, int $count): void
{
    $products = ['Laptop', 'Phone', 'Tablet', 'Monitor', 'Keyboard', 'Mouse', 'Headphones', 'Webcam', 'Speaker', 'Charger'];
    $brands = ['Apple', 'Samsung', 'Dell', 'HP', 'Lenovo', 'Sony', 'LG', 'Asus'];

    $stmt = $pdo->prepare(
        'INSERT INTO products (id, tenant_id, sku, name_translations, description_translations, price_amount, price_currency, status, created_at, updated_at)
         VALUES (:id, :tenant_id, :sku, :name_translations, :description_translations, :price_amount, :price_currency, :status, NOW(), NOW())'
    );

    for ($i = 0; $i < $count; ++$i) {
        $product = $products[array_rand($products)];
        $brand = $brands[array_rand($brands)];
        $name = "$brand $product";
        $sku = sprintf('PROD-%06d', $i + 1);
        $price = rand(10, 2000) * 100; // $10 - $2000

        $stmt->execute([
            'id' => generateUuid(),
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name_translations' => json_encode(['en_US' => $name]),
            'description_translations' => json_encode(['en_US' => "High quality $name"]),
            'price_amount' => $price,
            'price_currency' => 'USD',
            'status' => 'active',
        ]);
    }
}

function generateTranslations(PDO $pdo, string $tenantId, int $count): void
{
    $domains = ['messages', 'validators', 'emails'];
    $keys = ['welcome', 'goodbye', 'hello', 'thank_you', 'error', 'success', 'warning', 'info'];
    $values = ['Welcome!', 'Goodbye!', 'Hello!', 'Thank you!', 'Error occurred', 'Success!', 'Warning', 'Information'];

    $stmt = $pdo->prepare(
        'INSERT INTO translations (id, tenant_id, domain, key, locale, value, created_at, updated_at)
         VALUES (:id, :tenant_id, :domain, :key, :locale, :value, NOW(), NOW())
         ON CONFLICT (tenant_id, domain, key, locale) DO NOTHING'
    );

    for ($i = 0; $i < $count; ++$i) {
        $stmt->execute([
            'id' => generateUuid(),
            'tenant_id' => $tenantId,
            'domain' => $domains[array_rand($domains)],
            'key' => $keys[array_rand($keys)].'_'.$i,
            'locale' => 'en_US',
            'value' => $values[array_rand($values)],
        ]);
    }
}

function generateOrders(PDO $pdo, string $tenantId, int $count): void
{
    $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

    $stmt = $pdo->prepare(
        'INSERT INTO orders (id, tenant_id, order_number, customer_email, status, total_amount, total_currency, created_at, updated_at)
         VALUES (:id, :tenant_id, :order_number, :customer_email, :status, :total_amount, :total_currency, NOW(), NOW())'
    );

    for ($i = 0; $i < $count; ++$i) {
        $orderNumber = sprintf('ORD-%08d', $i + 1);
        $customerEmail = "customer{$i}@example.com";
        $totalAmount = rand(50, 5000) * 100; // $50 - $5000

        $stmt->execute([
            'id' => generateUuid(),
            'tenant_id' => $tenantId,
            'order_number' => $orderNumber,
            'customer_email' => $customerEmail,
            'status' => $statuses[array_rand($statuses)],
            'total_amount' => $totalAmount,
            'total_currency' => 'USD',
        ]);
    }
}

function getStats(PDO $pdo, string $tenantId): array
{
    $tables = ['products', 'categories', 'orders', 'translations', 'warehouses'];
    $stats = [];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);
        $stats[$table] = $stmt->fetchColumn();
    }

    return $stats;
}

function generateUuid(): string
{
    return sprintf(
        '%08x-%04x-%04x-%04x-%012x',
        mt_rand(0, 0xFFFFFFFF),
        mt_rand(0, 0xFFFF),
        mt_rand(0, 0x0FFF) | 0x4000,
        mt_rand(0, 0x3FFF) | 0x8000,
        mt_rand(0, 0xFFFFFFFFFFFF)
    );
}
