<?php

declare(strict_types=1);

namespace App\Review\Infrastructure\Fixtures;

use App\DataFixtures\ProductFixtures;
use App\DataFixtures\TenantFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Review Fixtures - Creates product reviews using direct SQL.
 */
final class ReviewFixtures extends Fixture implements DependentFixtureInterface
{
    private const REVIEW_TEMPLATES = [
        ['rating' => 5, 'title' => 'Excellent product!', 'content' => 'This product exceeded my expectations. High quality and great value for money. Highly recommended!'],
        ['rating' => 5, 'title' => 'Perfect!', 'content' => 'Exactly what I was looking for. Fast shipping and excellent quality. Will buy again.'],
        ['rating' => 4, 'title' => 'Very good', 'content' => 'Good product overall. Minor issues but nothing major. Worth the price.'],
        ['rating' => 4, 'title' => 'Satisfied', 'content' => 'Met my expectations. Good quality and fast delivery.'],
        ['rating' => 3, 'title' => 'Average', 'content' => 'Product is okay. Does the job but nothing special.'],
        ['rating' => 2, 'title' => 'Not great', 'content' => 'Product has some issues. Expected better quality.'],
        ['rating' => 1, 'title' => 'Disappointed', 'content' => 'Not satisfied with this purchase. Quality is poor.'],
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
            ProductFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $tenantIdString = $this->connection->fetchOne('SELECT id FROM tenants ORDER BY created_at LIMIT 1');
        if (!$tenantIdString) {
            echo "   No tenants found. Skipping review fixtures.\n";

            return;
        }

        // Set RLS context
        $this->connection->executeStatement("SET app.tenant_id = '{$tenantIdString}'");

        $productIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM catalog_products WHERE tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 100',
            ['tenant_id' => $tenantIdString]
        );

        if (empty($productIds)) {
            echo "   No products found. Skipping review fixtures.\n";

            return;
        }

        echo "Creating product reviews...\n";

        $reviewCount = 0;

        foreach ($productIds as $productId) {
            // Skip some products randomly (40% chance to skip)
            if (mt_rand(1, 100) <= 40) {
                continue;
            }

            $numReviews = mt_rand(1, 5);

            for ($i = 0; $i < $numReviews; ++$i) {
                $template = self::REVIEW_TEMPLATES[array_rand(self::REVIEW_TEMPLATES)];
                $isVerified = mt_rand(1, 100) <= 70;

                $this->connection->executeStatement(
                    'INSERT INTO product_reviews (id, tenant_id, product_id, customer_id, rating, title, content, is_verified, is_approved, created_at, updated_at)
                     VALUES (:id, :tenant_id, :product_id, :customer_id, :rating, :title, :content, :is_verified, true, NOW(), NOW())',
                    [
                        'id' => (string) Uuid::v7(),
                        'tenant_id' => $tenantIdString,
                        'product_id' => $productId,
                        'customer_id' => null,
                        'rating' => $template['rating'],
                        'title' => $template['title'],
                        'content' => $template['content'],
                        'is_verified' => $isVerified ? 'true' : 'false',
                    ]
                );
                ++$reviewCount;
            }
        }

        echo "Created {$reviewCount} product reviews\n";
    }
}
