<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\VariantEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider for single variant item
 */
final readonly class VariantItemProvider implements ProviderInterface
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository,
        private RequestStack $requestStack
    ) {}

    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): ?VariantEntity {
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

        // Find configurable product
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            ProductId::fromString($productIdString),
            TenantId::fromString($tenantIdString)
        );

        if (!$configurableProduct) {
            return null;
        }

        // Find the variant
        $variant = null;
        foreach ($configurableProduct->getVariants() as $v) {
            if ($v->getId()->toString() === $variantIdString) {
                $variant = $v;
                break;
            }
        }

        if (!$variant) {
            return null;
        }

        // Convert to entity
        $entity = new VariantEntity();
        $reflection = new \ReflectionClass($entity);

        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($entity, $variant->getId()->toString());

        $skuProperty = $reflection->getProperty('sku');
        $skuProperty->setAccessible(true);
        $skuProperty->setValue($entity, $variant->getSku()->toString());

        $optionValueMapProperty = $reflection->getProperty('optionValueMap');
        $optionValueMapProperty->setAccessible(true);
        $optionValueMapProperty->setValue($entity, $variant->getOptionValueMap());

        $priceAmountProperty = $reflection->getProperty('priceAmount');
        $priceAmountProperty->setAccessible(true);
        $priceAmountProperty->setValue($entity, $variant->getPrice()->getAmount());

        $priceCurrencyProperty = $reflection->getProperty('priceCurrency');
        $priceCurrencyProperty->setAccessible(true);
        $priceCurrencyProperty->setValue($entity, $variant->getPrice()->getCurrency()->getCurrencyCode());

        $stockOnHandProperty = $reflection->getProperty('stockOnHand');
        $stockOnHandProperty->setAccessible(true);
        $stockOnHandProperty->setValue($entity, $variant->getStock()->quantity());

        $trackInventoryProperty = $reflection->getProperty('trackInventory');
        $trackInventoryProperty->setAccessible(true);
        $trackInventoryProperty->setValue($entity, $variant->getStock()->trackInventory());

        $allowBackorderProperty = $reflection->getProperty('allowBackorder');
        $allowBackorderProperty->setAccessible(true);
        $allowBackorderProperty->setValue($entity, $variant->getStock()->allowBackorder());

        $isActiveProperty = $reflection->getProperty('isActive');
        $isActiveProperty->setAccessible(true);
        $isActiveProperty->setValue($entity, $variant->isActive());

        $imagesProperty = $reflection->getProperty('images');
        $imagesProperty->setAccessible(true);
        $imagesProperty->setValue($entity, array_map(fn($img) => $img->toArray(), $variant->getImages()));

        return $entity;
    }
}
