<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Query\CheckStockAvailability\CheckStockAvailabilityQuery;
use App\Inventory\Application\Query\CheckStockAvailability\StockAvailabilityItem;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockAvailabilityItemResource;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockAvailabilityItemResultResource;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockAvailabilityResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Processor for checking stock availability.
 *
 * Delegates to CheckStockAvailabilityHandler and transforms result to API response.
 *
 * @implements ProcessorInterface<StockAvailabilityResource, StockAvailabilityResource>
 */
final readonly class CheckStockAvailabilityProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StockAvailabilityResource
    {
        assert($data instanceof StockAvailabilityResource);

        // Validate input
        if (null === $data->items || [] === $data->items) {
            throw new BadRequestHttpException('Items array cannot be empty');
        }

        // Get tenant from context
        $tenantId = $this->getTenantIdFromContext($context);

        // Transform API request items to query items
        /** @var array<StockAvailabilityItemResource> $items */
        $items = $data->items;
        $queryItems = array_map(
            function (StockAvailabilityItemResource $item) {
                $productId = $item->productId;
                $variantId = $item->variantId;
                $quantity = $item->quantity;

                if (null === $productId) {
                    throw new BadRequestHttpException('productId is required for each item');
                }
                if (null === $quantity) {
                    throw new BadRequestHttpException('quantity is required for each item');
                }

                return new StockAvailabilityItem(
                    productId: ProductId::fromString($productId),
                    variantId: $variantId,
                    quantity: $quantity,
                );
            },
            $items
        );

        // Dispatch query
        $query = new CheckStockAvailabilityQuery(
            items: $queryItems,
            tenantId: $tenantId,
        );

        $envelope = $this->messageBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (null === $handledStamp) {
            throw new \RuntimeException('Query handler did not return a result');
        }

        $result = $handledStamp->getResult();

        // Transform result to API response
        $responseItems = array_map(
            fn ($item) => new StockAvailabilityItemResultResource(
                productId: $item->productId,
                variantId: $item->variantId,
                requestedQuantity: $item->requestedQuantity,
                availableQuantity: $item->availableQuantity,
                available: $item->available,
            ),
            $result->items
        );

        return new StockAvailabilityResource(
            items: $responseItems,
            available: $result->available,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getTenantIdFromContext(array $context): TenantId
    {
        if (isset($context['tenant_id'])) {
            return TenantId::fromString($context['tenant_id']);
        }

        throw new BadRequestHttpException('Tenant ID not found in context. Ensure X-Tenant-ID header is provided.');
    }
}
