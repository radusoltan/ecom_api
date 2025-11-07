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
        private RequestStack $requestStack
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
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

        // Get product ID and variant ID from URI variables
        if (!isset($uriVariables['productId'])) {
            throw new \InvalidArgumentException('Product ID is required in URI');
        }

        if (!isset($uriVariables['id'])) {
            throw new \InvalidArgumentException('Variant ID is required in URI');
        }

        $productIdString = (string) $uriVariables['productId'];
        $variantIdString = (string) $uriVariables['id'];

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
