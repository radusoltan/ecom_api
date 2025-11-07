#!/usr/bin/env php
<?php

/**
 * Test Data Generation Script v2 - Matches Actual Schema.
 *
 * Generates realistic test data for performance testing.
 *
 * Usage:
 *   php tests/Performance/scripts/generate_test_data_v2.php [size]
 *
 * Sizes: small, medium, large
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
    echo "✓ Connected to database\n";
} catch (PDOException $e) {
    echo '❌ Database connection failed: '.$e->getMessage()."\n";
    exit(1);
}

// Parse arguments
$size = $argv[1] ?? 'medium';

$config = match ($size) {
    'small' => ['products' => 100, 'categories' => 20, 'orders' => 50, 'warehouses' => 3],
    'medium' => ['products' => 500, 'categories' => 30, 'orders' => 100, 'warehouses' => 5],
    'large' => ['products' => 1000, 'categories' => 50, 'orders' => 200, 'warehouses' => 10],
    default => throw new InvalidArgumentException("Invalid size: $size (use: small, medium, large)"),
};

echo "\n🚀 Generating test data (size: $size)\n";
echo "   Products: {$config['products']}\n";
echo "   Categories: {$config['categories']}\n";
echo "   Orders: {$config['orders']}\n";
echo "   Warehouses: {$config['warehouses']}\n\n";

// Get or create test tenant
$tenantId = getOrCreateTestTenant($pdo);
echo "✓ Using tenant: $tenantId\n\n";

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

    // 4. Generate Orders
    echo '📋 Generating orders... ';
    generateOrders($pdo, $tenantId, $config['orders']);
    echo "✓ {$config['orders']} orders created\n";

    // Commit transaction
    $pdo->commit();

    echo "\n✅ Test data generation complete!\n\n";
    echo "📊 Summary:\n";

    $stats = getStats($pdo, $tenantId);
    foreach ($stats as $table => $count) {
        echo "   $table: $count\n";
    }

    echo "\n💡 Usage:\n";
    echo "   export TENANT_ID='$tenantId'\n";
    echo "   export BASE_URL='http://localhost:8000'\n";
    echo "   k6 run tests/Performance/k6/smoke_test.js\n";
    echo "   k6 run tests/Performance/k6/api_load_test.js\n\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo '❌ Error: '.$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
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
        'INSERT INTO tenants (id, name, slug, owner_email, status, created_at)
         VALUES (:id, :name, :slug, :owner_email, :status, NOW())'
    );
    $stmt->execute([
        'id' => $id,
        'name' => 'Load Test Tenant',
        'slug' => 'load-test-tenant',
        'owner_email' => 'loadtest@example.com',
        'status' => 'active',
    ]);

    return $id;
}

function generateCategories(PDO $pdo, string $tenantId, int $count): array
{
    $categoryIds = [];
    $categories = [
        'Electronics', 'Computers', 'Laptops', 'Desktops', 'Monitors',
        'Phones', 'Smartphones', 'Tablets', 'Accessories', 'Keyboards',
        'Mice', 'Headphones', 'Speakers', 'Webcams', 'Chargers',
        'Cables', 'Cases', 'Screen Protectors', 'Power Banks', 'Stands',
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO catalog_categories (id, tenant_id, name, slug, position, active, created_at, updated_at)
         VALUES (:id, :tenant_id, :name, :slug, :position, :active, NOW(), NOW())'
    );

    for ($i = 0; $i < min($count, count($categories)); ++$i) {
        $id = generateUuid();
        $name = $categories[$i];
        $slug = strtolower(str_replace(' ', '-', $name)).'-'.$i;

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'position' => $i + 1,
            'active' => 't',
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
        ['name' => 'North Warehouse', 'code' => 'WH-NORTH', 'city' => 'Seattle'],
        ['name' => 'Midwest Hub', 'code' => 'WH-MIDWEST', 'city' => 'Detroit'],
        ['name' => 'Southwest Hub', 'code' => 'WH-SW', 'city' => 'Phoenix'],
        ['name' => 'Northwest Hub', 'code' => 'WH-NW', 'city' => 'Portland'],
        ['name' => 'Southeast Hub', 'code' => 'WH-SE', 'city' => 'Atlanta'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO warehouses (id, tenant_id, name, code, address, priority, is_active, created_at, updated_at)
         VALUES (:id, :tenant_id, :name, :code, :address, :priority, :is_active, NOW(), NOW())'
    );

    for ($i = 0; $i < min($count, count($warehouses)); ++$i) {
        $w = $warehouses[$i];
        $stmt->execute([
            'id' => generateUuid(),
            'tenant_id' => $tenantId,
            'name' => $w['name'],
            'code' => $w['code'],
            'address' => json_encode(['city' => $w['city'], 'country' => 'USA']),
            'priority' => $count - $i,
            'is_active' => 't',
        ]);
    }
}

function generateProducts(PDO $pdo, string $tenantId, array $categoryIds, int $count): void
{
    $products = ['Laptop', 'Desktop', 'Phone', 'Tablet', 'Monitor', 'Keyboard', 'Mouse', 'Headphones', 'Webcam', 'Speaker'];
    $brands = ['Apple', 'Samsung', 'Dell', 'HP', 'Lenovo', 'Sony', 'LG', 'Asus', 'Acer', 'MSI'];
    $adjectives = ['Pro', 'Ultra', 'Max', 'Plus', 'Elite', 'Premium', 'Standard', 'Basic', 'Advanced', 'Gaming'];

    $stmt = $pdo->prepare(
        'INSERT INTO catalog_products (id, tenant_id, sku, name, description, short_description, slug, price_amount, price_currency, category_id, stock_quantity, track_inventory, allow_backorder, images, active, created_at, updated_at)
         VALUES (:id, :tenant_id, :sku, :name, :description, :short_description, :slug, :price_amount, :price_currency, :category_id, :stock_quantity, :track_inventory, :allow_backorder, :images, :active, NOW(), NOW())'
    );

    for ($i = 0; $i < $count; ++$i) {
        $product = $products[array_rand($products)];
        $brand = $brands[array_rand($brands)];
        $adjective = $adjectives[array_rand($adjectives)];
        $name = "$brand $product $adjective";
        $sku = sprintf('PROD-%06d', $i + 1);
        $slug = strtolower(str_replace(' ', '-', $name)).'-'.$i;
        $price = rand(10, 2000) * 100; // $10 - $2000 in cents
        $categoryId = $categoryIds[array_rand($categoryIds)];

        $stmt->execute([
            'id' => generateUuid(),
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => $name,
            'description' => "High quality $name with advanced features and reliable performance.",
            'short_description' => "High quality $name",
            'slug' => $slug,
            'price_amount' => $price,
            'price_currency' => 'USD',
            'category_id' => $categoryId,
            'stock_quantity' => rand(0, 1000),
            'track_inventory' => 't',
            'allow_backorder' => 'f',
            'images' => json_encode([]),
            'active' => 't',
        ]);
    }
}

function generateOrders(PDO $pdo, string $tenantId, int $count): void
{
    $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

    $stmt = $pdo->prepare(
        'INSERT INTO orders (id, tenant_id, customer_email, status, lines, shipping_address, billing_address, created_at, updated_at)
         VALUES (:id, :tenant_id, :customer_email, :status, :lines, :shipping_address, :billing_address, NOW(), NOW())'
    );

    for ($i = 0; $i < $count; ++$i) {
        $customerEmail = "customer{$i}@example.com";

        $stmt->execute([
            'id' => generateUuid(),
            'tenant_id' => $tenantId,
            'customer_email' => $customerEmail,
            'status' => $statuses[array_rand($statuses)],
            'lines' => json_encode([]),
            'shipping_address' => json_encode(['city' => 'New York', 'country' => 'USA']),
            'billing_address' => json_encode(['city' => 'New York', 'country' => 'USA']),
        ]);
    }
}

function getStats(PDO $pdo, string $tenantId): array
{
    $tables = [
        'catalog_products' => 'products',
        'catalog_categories' => 'categories',
        'orders' => 'orders',
        'warehouses' => 'warehouses',
    ];
    $stats = [];

    foreach ($tables as $table => $label) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);
        $stats[$label] = $stmt->fetchColumn();
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
