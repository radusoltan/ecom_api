<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\BulkImportProducts;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for bulk importing products
 */
#[AsMessageHandler]
final readonly class BulkImportProductsHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {
    }

    public function __invoke(BulkImportProductsCommand $command): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($command->products as $index => $productData) {
            try {
                // Validate required fields
                if (empty($productData['name'])) {
                    $errors[] = [
                        'row' => $index + 1,
                        'sku' => $productData['sku'] ?? null,
                        'message' => 'Product name is required'
                    ];
                    $failed++;
                    continue;
                }

                if (!isset($productData['priceAmount']) || $productData['priceAmount'] <= 0) {
                    $errors[] = [
                        'row' => $index + 1,
                        'sku' => $productData['sku'] ?? null,
                        'message' => 'Valid price amount is required'
                    ];
                    $failed++;
                    continue;
                }

                // Extract tenant ID from context (should be set by middleware)
                // For now, use a default tenant ID - TODO: Get from authenticated context
                $tenantIdString = $productData['tenantId'] ?? '51bd1332-307c-480c-bd3c-6bfe5ccd7829';
                $tenantId = TenantId::fromString($tenantIdString);

                // Create product
                $product = Product::create(
                    name: $productData['name'],
                    description: $productData['description'] ?? null,
                    shortDescription: $productData['shortDescription'] ?? null,
                    priceAmount: (int) $productData['priceAmount'],
                    priceCurrency: $productData['priceCurrency'] ?? 'USD',
                    tenantId: $tenantId,
                    categoryId: $productData['categoryId'] ?? null,
                    stockQuantity: (int) ($productData['stockQuantity'] ?? 0),
                    trackInventory: (bool) ($productData['trackInventory'] ?? true),
                    allowBackorder: (bool) ($productData['allowBackorder'] ?? false),
                    active: (bool) ($productData['active'] ?? true),
                    isFeatured: (bool) ($productData['isFeatured'] ?? false)
                );

                // Save product
                $this->productRepository->save($product);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'sku' => $productData['sku'] ?? null,
                    'message' => $e->getMessage()
                ];
                $failed++;
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
}
