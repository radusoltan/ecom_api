<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Main AppFixtures - orchestrates all fixture loading
 *
 * This is the entry point for loading all fixtures in the correct order:
 * 1. TenantFixtures - Creates tenants
 * 2. UserFixtures - Creates admin users
 * 3. CategoryFixtures - Creates product categories with translations
 * 4. ProductFixtures - Creates products with translations and images
 * 5. CustomerFixtures - Creates customers
 * 6. WarehouseFixtures - Creates warehouses
 * 7. StockItemFixtures - Creates inventory across warehouses
 * 8. PriceListFixtures - Creates pricing rules
 * 9. OrderFixtures - Creates sample orders
 *
 * Usage:
 *   symfony console doctrine:fixtures:load --no-interaction
 *
 * Summary:
 * - 3 Tenants
 * - 5 Users (1 super admin + 4 tenant admins/staff)
 * - 12 Categories (3 root + 9 subcategories) with EN/FR/DE translations
 * - 108 Products with 1-5 images each and EN/FR/DE translations
 * - 15 Customers (8 regular, 5 VIP, 2 premium)
 * - 5 Warehouses
 * - 90 Stock Items (30 products × 3 warehouses)
 * - 4 Price Lists
 * - 20 Orders with various statuses
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║          E-COMMERCE PLATFORM - FIXTURES LOADER             ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "📋 Loading fixtures in order...\n";
        echo "\n";
        echo "This will create:\n";
        echo "  • 3 Tenants (TechMart, Fashion Hub, HomeGoods Plus)\n";
        echo "  • 5 Users (admin@admin.com / password + tenant admins)\n";
        echo "  • 12 Categories (with EN/FR/DE translations + images)\n";
        echo "  • 108 Products (with 1-5 images + EN/FR/DE translations)\n";
        echo "  • 15 Customers (various segments)\n";
        echo "  • 5 Warehouses (across US regions)\n";
        echo "  • 90 Stock Items (inventory management)\n";
        echo "  • 4 Price Lists (pricing rules)\n";
        echo "  • 20 Orders (various statuses)\n";
        echo "\n";
        echo "────────────────────────────────────────────────────────────\n";
        echo "\n";

        // Note: Individual fixtures will be loaded automatically by Doctrine
        // in the order specified by their getOrder() methods and dependencies

        echo "\n";
        echo "────────────────────────────────────────────────────────────\n";
        echo "\n";
        echo "✨ Fixture loading completed!\n";
        echo "\n";
        echo "🔐 Login credentials:\n";
        echo "   Super Admin: admin@admin.com / password\n";
        echo "   TechMart Admin: admin@techmart.com / password\n";
        echo "   Fashion Hub Admin: admin@fashionhub.com / password\n";
        echo "   HomeGoods Admin: admin@homegoods.com / password\n";
        echo "   Staff: staff@techmart.com / password\n";
        echo "\n";
        echo "🌐 API Endpoints:\n";
        echo "   Backend: http://localhost:8000\n";
        echo "   Admin Frontend: http://localhost:3001\n";
        echo "   API Docs: http://localhost:8000/api/docs\n";
        echo "\n";
        echo "📊 Quick Stats:\n";
        echo "   Total Products: 108\n";
        echo "   Total Categories: 12\n";
        echo "   Total Orders: 20\n";
        echo "   Total Customers: 15\n";
        echo "   Total Warehouses: 5\n";
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║                    READY FOR TESTING! 🚀                   ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }

    public function getOrder(): int
    {
        return 100; // Run last to display summary
    }
}
