<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\UpdateVariant;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\VariantEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for updating variants.
 */
final readonly class UpdateVariantProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): VariantEntity {
        if (!$data instanceof VariantEntity) {
            throw new \InvalidArgumentException('Expected VariantEntity');
        }

        // Get tenant ID from request header
        $request = $this->requestStack->getCurrentRequest();
        $tenantIdString = $request?->headers->get('X-Tenant-ID');

        if (!$tenantIdString) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        // Get variant ID from URI variables
        if (!isset($uriVariables['id'])) {
            throw new \InvalidArgumentException('Variant ID is required in URI');
        }

        $variantIdString = (string) $uriVariables['id'];

        // Get product ID from entity or query parameter
        $productIdString = null;

        // Try to get from relationship if available
        $configurableProduct = $data->getConfigurableProduct();
        if ($configurableProduct) {
            $productIdString = $configurableProduct->getId();
        }

        // Fallback to productId from entity's temporary field
        if (!$productIdString && $data->productId) {
            $productIdString = $data->productId;
        }

        // Fallback to query parameter
        if (!$productIdString && $request) {
            $queryProductId = $request->query->get('productId');
            $productIdString = is_string($queryProductId) ? $queryProductId : null;
        }

        if (!$productIdString) {
            throw new \RuntimeException('Product ID is required (provide as productId in request body or query parameter)');
        }

        // Create command with updated values
        $command = new UpdateVariant(
            variantId: VariantId::fromString($variantIdString),
            productId: ProductId::fromString($productIdString),
            tenantId: TenantId::fromString($tenantIdString),
            price: Money::fromScalars($data->getPriceAmount(), $data->getPriceCurrency()),
            stockQuantity: $data->getStockOnHand(),
            isActive: $data->isActive(),
            trackInventory: null, // Keep existing if not provided
            allowBackorder: null, // Keep existing if not provided
            images: null // Keep existing if not provided
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        return $data;
    }
}
