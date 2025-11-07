<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;

/**
 * ConfigureSubscriptionCommandHandler.
 *
 * Handles configuration of subscriptions for products.
 */
final readonly class ConfigureSubscriptionCommandHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {
    }

    public function __invoke(ConfigureSubscriptionCommand $command): void
    {
        // Load the product
        $product = $this->productRepository->findById($command->productId);

        if ($product === null) {
            throw new \DomainException(
                sprintf('Product with ID "%s" not found', $command->productId->toString())
            );
        }

        // Configure subscription on product (validates business rules)
        $product->configureSubscription(
            $command->interval,
            $command->billingCycles,
            $command->setupFee,
            $command->trialPeriodEnd
        );

        // Save product (will dispatch domain events)
        $this->productRepository->save($product);
    }
}
