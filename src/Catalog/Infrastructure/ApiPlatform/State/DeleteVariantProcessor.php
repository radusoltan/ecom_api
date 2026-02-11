<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\DeleteVariant;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for deleting variants.
 */
final readonly class DeleteVariantProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): void {
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

        // For DELETE, $data will be the entity loaded by API Platform
        // We need to check if it's a VariantEntity to get the product ID
        $productIdString = null;

        if ($data instanceof \App\Catalog\Infrastructure\Persistence\Doctrine\Entity\VariantEntity) {
            $configurableProduct = $data->getConfigurableProduct();
            if ($configurableProduct) {
                $productIdString = $configurableProduct->getId();
            }

            // Fallback to productId from entity's temporary field
            if (!$productIdString && $data->productId) {
                $productIdString = $data->productId;
            }
        }

        // Fallback to query parameter
        if (!$productIdString && $request) {
            $queryProductId = $request->query->get('productId');
            $productIdString = is_string($queryProductId) ? $queryProductId : null;
        }

        if (!$productIdString) {
            throw new \RuntimeException('Product ID is required (provide as productId query parameter)');
        }

        // Create and dispatch command
        $command = new DeleteVariant(
            variantId: VariantId::fromString($variantIdString),
            productId: ProductId::fromString($productIdString),
            tenantId: TenantId::fromString($tenantIdString)
        );

        $this->commandBus->dispatch($command);
    }
}
