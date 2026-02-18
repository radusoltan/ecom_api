<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\CreateVariant;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\VariantEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Processor for creating variants.
 */
final readonly class CreateVariantProcessor implements ProcessorInterface
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

        // Get product ID from request body
        if (!$data->productId) {
            throw new \InvalidArgumentException('Product ID is required in request body');
        }

        $productIdString = $data->productId;

        // Generate variant ID if not provided
        $variantIdString = Uuid::v4()->toString();

        // Create command
        $command = new CreateVariant(
            variantId: VariantId::fromString($variantIdString),
            productId: ProductId::fromString($productIdString),
            tenantId: TenantId::fromString($tenantIdString),
            sku: VariantSKU::fromString($data->getSku()),
            optionValueMap: $data->getOptionValueMap(),
            price: Money::fromScalars($data->getPriceAmount(), $data->getPriceCurrency()),
            stockQuantity: $data->getStockOnHand(),
            trackInventory: true, // Default from entity
            allowBackorder: false, // Default from entity
            isActive: $data->isActive()
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Set the generated ID on the entity using reflection
        $reflection = new \ReflectionClass($data);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($data, $variantIdString);

        return $data;
    }
}
