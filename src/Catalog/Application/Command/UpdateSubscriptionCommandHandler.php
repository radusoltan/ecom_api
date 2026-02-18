<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;

/**
 * UpdateSubscriptionCommandHandler.
 *
 * Handles updating of subscription configurations.
 */
final readonly class UpdateSubscriptionCommandHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(UpdateSubscriptionCommand $command): void
    {
        // Load the product
        $product = $this->productRepository->findById($command->productId);

        if (null === $product) {
            throw new \DomainException(sprintf('Product with ID "%s" not found', $command->productId->toString()));
        }

        // Update subscription on product (validates business rules)
        $product->updateSubscription(
            $command->interval,
            $command->billingCycles,
            $command->setupFee,
            $command->trialPeriodEnd
        );

        // Save product (will dispatch domain events)
        $this->productRepository->save($product);
    }
}
