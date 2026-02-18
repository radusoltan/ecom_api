<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\DTO\VariantDTO;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetVariantsByProduct query.
 */
#[AsMessageHandler]
final readonly class GetVariantsByProductHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository,
    ) {
    }

    /**
     * @return array{variants: VariantDTO[], total: int, page: int, pages: int}
     */
    public function __invoke(GetVariantsByProduct $query): array
    {
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            $query->productId,
            $query->tenantId
        );

        if (!$configurableProduct) {
            return [
                'variants' => [],
                'total' => 0,
                'page' => $query->page,
                'pages' => 0,
            ];
        }

        $allVariants = [];

        foreach ($configurableProduct->getVariants() as $variant) {
            // Filter by active status if specified
            if (null !== $query->activeOnly && $variant->isActive() !== $query->activeOnly) {
                continue;
            }

            // Filter by option values
            if (!empty($query->filters)) {
                $matches = true;
                foreach ($query->filters as $optionCode => $valueCode) {
                    $variantValueMap = $variant->getOptionValueMap();
                    if (!isset($variantValueMap[$optionCode]) || $variantValueMap[$optionCode] !== $valueCode) {
                        $matches = false;

                        break;
                    }
                }
                if (!$matches) {
                    continue;
                }
            }

            $allVariants[] = new VariantDTO(
                id: $variant->getId()->toString(),
                sku: $variant->getSku()->toString(),
                optionValueMap: $variant->getOptionValueMap(),
                priceAmount: $variant->getPrice()->getAmount(),
                priceCurrency: $variant->getPrice()->getCurrency()->getCurrencyCode(),
                stockQuantity: $variant->getStock()->quantity(),
                trackInventory: $variant->getStock()->trackInventory(),
                allowBackorder: $variant->getStock()->allowBackorder(),
                isActive: $variant->isActive(),
                isAvailable: $variant->isAvailable(),
                images: array_map(fn ($img) => $img->toArray(), $variant->getImages())
            );
        }

        // Paginate results
        $total = count($allVariants);
        $pages = (int) ceil($total / $query->limit);
        $offset = ($query->page - 1) * $query->limit;
        $paginatedVariants = array_slice($allVariants, $offset, $query->limit);

        return [
            'variants' => $paginatedVariants,
            'total' => $total,
            'page' => $query->page,
            'pages' => $pages,
        ];
    }
}
