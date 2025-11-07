<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;

/**
 * GetBundleItemsQueryHandler.
 *
 * Returns bundle items for a product.
 *
 * @return array{items: array<array{productId: string, quantity: int, priceAmount: int, priceCurrency: string}>, discountPercentage: float, calculatedPrice: array{amount: int, currency: string}}|null
 */
final readonly class GetBundleItemsQueryHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @return array{items: array<array{productId: string, quantity: int, priceAmount: int, priceCurrency: string}>, discountPercentage: float, calculatedPrice: array{amount: int, currency: string}}|null
     */
    public function __invoke(GetBundleItemsQuery $query): ?array
    {
        // Load the product
        $product = $this->productRepository->findById($query->bundleProductId);

        if ($product === null) {
            throw new \DomainException(
                sprintf('Product with ID "%s" not found', $query->bundleProductId->toString())
            );
        }

        // Check if product has bundle
        if (!$product->hasBundle()) {
            return null;
        }

        $bundle = $product->bundle();

        if ($bundle === null) {
            return null;
        }

        // Convert bundle to array representation
        $items = [];
        foreach ($bundle->items() as $item) {
            $items[] = [
                'productId' => $item->productId()->toString(),
                'quantity' => $item->quantity(),
                'priceAmount' => $item->price()->getAmount(),
                'priceCurrency' => $item->price()->getCurrency(),
            ];
        }

        $calculatedPrice = $bundle->calculatePrice();

        return [
            'items' => $items,
            'discountPercentage' => $bundle->discountPercentage(),
            'calculatedPrice' => [
                'amount' => $calculatedPrice->getAmount(),
                'currency' => $calculatedPrice->getCurrency(),
            ],
        ];
    }
}
