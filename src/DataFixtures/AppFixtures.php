<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-summary'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "\n";
        echo "============================================================\n";
        echo "Sprint 3 Realistic Seed Verification\n";
        echo "============================================================\n";
        echo "\n";
        echo "RLS per-tenant counts:\n";

        $minimums = Sprint3SeedData::minimumCountsPerTenant();
        $tenantIds = $this->tenantIdsByOwnerEmail();
        $sampleProductIds = [];

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = $tenantIds[$tenant['ownerEmail']];
            $this->activateTenantContext($tenantId);

            foreach ($minimums as $table => $expectedMinimum) {
                $count = (int) $this->connection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
                if ($count < $expectedMinimum) {
                    throw new \RuntimeException(sprintf('Tenant %s sees only %d rows in %s, expected at least %d', $tenant['name'], $count, $table, $expectedMinimum));
                }
            }

            $sampleProductIds[$tenant['alias']] = (string) $this->connection()->fetchOne(
                'SELECT id FROM catalog_products ORDER BY created_at ASC LIMIT 1'
            );

            $translatedProductCount = (int) $this->connection()->fetchOne(
                "SELECT COUNT(*) FROM catalog_products
                WHERE jsonb_exists(name_translations, 'en')
                  AND jsonb_exists(name_translations, 'fr')
                  AND jsonb_exists(name_translations, 'de')
                  AND jsonb_exists(description_translations, 'en')
                  AND jsonb_exists(description_translations, 'fr')
                  AND jsonb_exists(description_translations, 'de')
                  AND jsonb_exists(short_description_translations, 'en')
                  AND jsonb_exists(short_description_translations, 'fr')
                  AND jsonb_exists(short_description_translations, 'de')"
            );
            if ($translatedProductCount < $minimums['catalog_products']) {
                throw new \RuntimeException(sprintf('Tenant %s has only %d fully translated products, expected at least %d', $tenant['name'], $translatedProductCount, $minimums['catalog_products']));
            }

            $translatedCategoryCount = (int) $this->connection()->fetchOne(
                "SELECT COUNT(*) FROM catalog_categories
                WHERE jsonb_exists(name_translations, 'en')
                  AND jsonb_exists(name_translations, 'fr')
                  AND jsonb_exists(name_translations, 'de')
                  AND jsonb_exists(description_translations, 'en')
                  AND jsonb_exists(description_translations, 'fr')
                  AND jsonb_exists(description_translations, 'de')"
            );
            if ($translatedCategoryCount < $minimums['catalog_categories']) {
                throw new \RuntimeException(sprintf('Tenant %s has only %d fully translated categories, expected at least %d', $tenant['name'], $translatedCategoryCount, $minimums['catalog_categories']));
            }

            echo sprintf(
                "  - %s: %d categories, %d products, %d options, %d variants, %d customers, %d orders, %d invoices, %d promotions\n",
                $tenant['name'],
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_categories'),
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_products'),
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_product_options'),
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_product_variants'),
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM customers'),
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM orders'),
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM invoices'),
                (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM promotions'),
            );
        }

        $tenants = array_values(Sprint3SeedData::tenants());
        foreach ($tenants as $viewer) {
            $this->activateTenantContext($tenantIds[$viewer['ownerEmail']]);

            foreach ($tenants as $owner) {
                if ($viewer['alias'] === $owner['alias']) {
                    continue;
                }

                $visibleCount = (int) $this->connection()->fetchOne(
                    'SELECT COUNT(*) FROM catalog_products WHERE id = :id',
                    ['id' => $sampleProductIds[$owner['alias']]]
                );

                if (0 !== $visibleCount) {
                    throw new \RuntimeException(sprintf('RLS verification failed: tenant %s can read product %s from tenant %s', $viewer['name'], $sampleProductIds[$owner['alias']], $owner['name']));
                }
            }
        }

        $this->clearTenantContext();

        echo "\n";
        echo "RLS verified across all three tenants.\n";
        echo "\n";
        echo "Seed summary:\n";
        echo "  - 3 tenants with EN/FR/DE locale support\n";
        echo "  - 54 categories with parent-child hierarchy\n";
        echo "  - 1,008 products with translated catalog content\n";
        echo "  - 360 configurable product options and 1,080 option values\n";
        echo "  - 1,080 variants across configurable products\n";
        echo "  - 216 customers with default and alternate addresses\n";
        echo "  - 510 orders across pending, processing, shipped, delivered, and cancelled states\n";
        echo "  - 108 invoices linked to orders\n";
        echo "  - 54 active, expired, and scheduled promotions\n";
        echo "\n";
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CategoryFixtures::class,
            ProductFixtures::class,
            ProductVariationsFixtures::class,
            WarehouseFixtures::class,
            InventoryFixtures::class,
            CustomerFixtures::class,
            PromotionFixtures::class,
            OrderFixtures::class,
            InvoiceFixtures::class,
        ];
    }
}
