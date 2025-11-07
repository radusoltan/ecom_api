<?php

declare(strict_types=1);

namespace App\Review\Infrastructure\Fixtures;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Command\SubmitReview;
use App\Review\Domain\ValueObject\Rating;
use App\Review\Domain\ValueObject\ReviewId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReviewFixtures extends Fixture
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

    private const CUSTOMER_NAMES = [
        'John Smith', 'Emma Johnson', 'Michael Brown', 'Sophia Davis', 'James Wilson',
        'Olivia Martinez', 'William Garcia', 'Ava Rodriguez', 'Robert Lee', 'Isabella Walker',
        'David Hall', 'Mia Allen', 'Richard Young', 'Charlotte King', 'Joseph Wright',
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $tenantId = TenantId::fromString('51bd1332-307c-480c-bd3c-6bfe5ccd7829');

        // Get all product IDs from database
        $productIds = $manager->getConnection()->executeQuery(
            'SELECT id FROM catalog_products WHERE tenant_id = :tenant_id AND active = true ORDER BY created_at DESC LIMIT 100',
            ['tenant_id' => $tenantId->toString()]
        )->fetchFirstColumn();

        $reviewCount = 0;

        // Create reviews for random products
        // Add 1-5 reviews per product, randomly selected (about 60% of products will have reviews)
        foreach ($productIds as $index => $productId) {
            // Skip some products randomly (40% chance to skip)
            if (mt_rand(1, 100) <= 40) {
                continue;
            }

            $numReviews = mt_rand(1, 5); // 1-5 reviews per product

            for ($i = 0; $i < $numReviews; ++$i) {
                $template = self::REVIEW_TEMPLATES[array_rand(self::REVIEW_TEMPLATES)];
                $customerName = self::CUSTOMER_NAMES[array_rand(self::CUSTOMER_NAMES)];
                $customerId = 'customer_'.md5($customerName.$productId.$i);

                // Some reviews are verified purchases (70% chance)
                $isVerifiedPurchase = mt_rand(1, 100) <= 70;

                $command = new SubmitReview(
                    reviewId: ReviewId::generate(),
                    productId: ProductId::fromString($productId),
                    tenantId: $tenantId,
                    customerId: $customerId,
                    customerName: $customerName,
                    rating: Rating::fromInt($template['rating']),
                    title: $template['title'],
                    content: $template['content'],
                    isVerifiedPurchase: $isVerifiedPurchase,
                );

                $this->commandBus->dispatch($command);
                ++$reviewCount;
            }
        }

        echo "\n✓ Created {$reviewCount} product reviews\n";

        // Auto-approve all reviews (in real system this would be manual/automated moderation)
        // $manager->getConnection()->executeStatement(
        //     "UPDATE product_reviews SET status = 'approved' WHERE tenant_id = :tenant_id",
        //     ['tenant_id' => $tenantId->toString()]
        // );

        // echo "✓ Approved all reviews\n";

        $manager->flush();
    }
}
