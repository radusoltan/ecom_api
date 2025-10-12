<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class OrderFixtures extends Fixture
{
    private string $tenantId;
    private array $productIds = [];

    public function load(ObjectManager $manager): void
    {
        // Get first tenant ID from database
        $this->tenantId = $this->getFirstTenantId($manager);

        if (!$this->tenantId) {
            echo "⚠️  No tenants found in database. Please create a tenant first.\n";
            return;
        }

        // Get product IDs
        $this->productIds = $this->getProductIds($manager);

        if (empty($this->productIds)) {
            echo "⚠️  No products found in database. Please create products first.\n";
            return;
        }

        echo "📦 Loading order fixtures for tenant: {$this->tenantId}\n";

        $this->loadOrders($manager);

        $manager->flush();

        echo "✅ Order fixtures loaded successfully!\n";
        echo "   - Orders: 10\n";
    }

    private function getFirstTenantId(ObjectManager $manager): ?string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants LIMIT 1')->fetchOne();

        return $result ?: null;
    }

    private function getProductIds(ObjectManager $manager): array
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM catalog_products LIMIT 10')->fetchAllAssociative();

        return array_column($result, 'id');
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
