<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Order fixtures - creates sample orders for testing
 */
class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        echo "📦 Creating orders...\n";

        // Get first tenant ID from database
        $connection = $manager->getConnection();
        $tenantId = $connection->executeQuery('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1')->fetchOne();

        // Set tenant context for RLS
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        $customerEmails = [
            'john.doe@example.com',
            'jane.smith@example.com',
            'william.garcia@example.com',
            'maria.rodriguez@example.com',
            'christopher.thomas@example.com',
        ];

        // Create 20 orders with varying statuses and dates
        for ($i = 0; $i < 20; $i++) {
            $orderId = Uuid::v4()->toString();
            $customerEmail = $customerEmails[$i % count($customerEmails)];
            $status = $statuses[$i % count($statuses)];

            // Get random products from database
            $productIds = $connection->executeQuery('SELECT id FROM catalog_products ORDER BY RANDOM() LIMIT ' . rand(1, 3))->fetchFirstColumn();
            $lines = [];

            foreach ($productIds as $productId) {
                $quantity = rand(1, 3);
                $unitPrice = rand(2000, 150000);

                $lines[] = [
                    'productId' => $productId,
                    'productName' => 'Product',
                    'quantity' => $quantity,
                    'unitPriceAmount' => $unitPrice,
                    'unitPriceCurrency' => 'USD',
                ];
            }

            if (empty($lines)) {
                continue; // Skip if no valid products
            }

            $shippingAddress = $this->getRandomAddress();
            $billingAddress = $shippingAddress; // Same as shipping for simplicity

            // Use reflection to create order
            $order = new OrderEntity();
            $reflection = new \ReflectionClass($order);

            $idProperty = $reflection->getProperty('id');
            $idProperty->setValue($order, $orderId);

            $tenantIdProperty = $reflection->getProperty('tenantId');
            $tenantIdProperty->setValue($order, $tenantId);

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

            // Orders from the past 30 days
            $daysAgo = rand(0, 30);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setValue($order, new DateTimeImmutable("-{$daysAgo} days"));

            $updatedAtProperty = $reflection->getProperty('updatedAt');
            $updatedAtProperty->setValue($order, new DateTimeImmutable("-{$daysAgo} days"));

            $manager->persist($order);
        }

        $manager->flush();
        echo "✅ Created 20 orders with various statuses\n";
    }

    private function getRandomProductReferences(int $count): array
    {
        $prefixes = ['lap', 'pho', 'acc', 'men', 'wom', 'kid', 'fur', 'dec', 'kit'];
        $refs = [];

        for ($i = 0; $i < $count; $i++) {
            $prefix = $prefixes[array_rand($prefixes)];
            $number = rand(1, 10);
            $refs[] = sprintf('product_%s_%d', $prefix, $number);
        }

        return $refs;
    }

    private function getRandomAddress(): array
    {
        $addresses = [
            [
                'street' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'postalCode' => '10001',
                'country' => 'US',
            ],
            [
                'street' => '456 Oak Avenue',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postalCode' => '90001',
                'country' => 'US',
            ],
            [
                'street' => '789 Pine Road',
                'city' => 'Chicago',
                'state' => 'IL',
                'postalCode' => '60601',
                'country' => 'US',
            ],
            [
                'street' => '321 Elm Street',
                'city' => 'Houston',
                'state' => 'TX',
                'postalCode' => '77001',
                'country' => 'US',
            ],
            [
                'street' => '654 Maple Drive',
                'city' => 'Seattle',
                'state' => 'WA',
                'postalCode' => '98101',
                'country' => 'US',
            ],
        ];

        return $addresses[array_rand($addresses)];
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
            ProductFixtures::class,
        ];
    }

    public function getOrder(): int
    {
        return 9;
    }
}
