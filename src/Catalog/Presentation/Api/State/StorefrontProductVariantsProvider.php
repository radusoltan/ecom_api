<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\Query\GetVariantsByProduct;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Presentation\Api\Resource\StorefrontProductVariantResource;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Storefront state provider for GET /api/v1/products/{productId}/variants.
 *
 * Supports ?activeOnly=true (default) and ?activeOnly=false query parameters.
 * Returns [] for simple products (no catalog_configurable_products entry).
 * Returns 404 for non-existent product UUID.
 * RLS isolation enforced via app.tenant_id GUC (set by TenantRequestSubscriber).
 *
 * @implements ProviderInterface<StorefrontProductVariantResource>
 */
final readonly class StorefrontProductVariantsProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContext $tenantContext,
        private RequestStack $requestStack,
        private Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<StorefrontProductVariantResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenantId = $this->resolveTenantId();
        $productId = $this->resolveProductId($uriVariables);

        // Verify product exists under this tenant (RLS scoped) — 404 for non-existent, [] for simple
        $this->assertProductExists($productId, $tenantId);

        $activeOnly = $this->resolveActiveOnly();

        $query = new GetVariantsByProduct(
            productId: ProductId::fromString($productId),
            tenantId: TenantId::fromString($tenantId),
            filters: [],
            activeOnly: $activeOnly,
        );

        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            return [];
        }

        $result = $stamp->getResult();
        /** @var \App\Catalog\Application\DTO\VariantDTO[] $variantDtos */
        $variantDtos = $result['variants'] ?? [];

        return array_values(array_map(
            fn ($dto) => $this->toResource($dto),
            $variantDtos
        ));
    }

    /**
     * Resolve tenant ID from TenantContext (set by TenantRequestSubscriber from X-Tenant-ID header).
     * Falls back to raw header if context not yet hydrated.
     */
    private function resolveTenantId(): string
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (null !== $tenantId && '' !== $tenantId) {
            return $tenantId;
        }

        $request = $this->requestStack->getCurrentRequest();
        $headerTenantId = $request?->headers->get('X-Tenant-ID');

        if (null === $headerTenantId || '' === $headerTenantId) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        return $headerTenantId;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function resolveProductId(array $uriVariables): string
    {
        $productId = $uriVariables['productId'] ?? null;

        if (!is_string($productId) || '' === $productId) {
            throw new NotFoundHttpException('Product not found');
        }

        return $productId;
    }

    /**
     * Resolve activeOnly filter from query string.
     * Default: true (only active variants shown to storefront).
     */
    /**
     * Resolve activeOnly filter from query string.
     *
     * Default: true (only active variants shown to storefront).
     * ?activeOnly=false: null (no filter — returns both active and inactive).
     * ?activeOnly=true: true (active only).
     */
    private function resolveActiveOnly(): ?bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return true;
        }

        $activeOnly = $request->query->get('activeOnly');

        if (null === $activeOnly) {
            return true; // default: active only
        }

        $value = filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN);

        // false means "include all" — map to null (no active filter in handler)
        return $value ? true : null;
    }

    /**
     * Assert product exists in catalog_products under the current tenant (RLS scoped).
     * 404 for non-existent UUID. Returns normally for simple products (no configurable entry).
     */
    private function assertProductExists(string $productId, string $tenantId): void
    {
        // Ensure RLS GUC is set before querying
        $this->connection->executeStatement(
            "SELECT set_config('app.tenant_id', :tenantId, false)",
            ['tenantId' => $tenantId]
        );

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM catalog_products WHERE id = :id AND active = true',
            ['id' => $productId]
        );

        if (0 === (int) $count) {
            throw new NotFoundHttpException('Product not found');
        }
    }

    private function toResource(\App\Catalog\Application\DTO\VariantDTO $dto): StorefrontProductVariantResource
    {
        $resource = new StorefrontProductVariantResource();
        $resource->id = $dto->id;
        $resource->sku = $dto->sku;
        $resource->optionValueMap = $dto->optionValueMap;
        $resource->priceAmount = $dto->priceAmount;
        $resource->priceCurrency = $dto->priceCurrency;
        $resource->stockQuantity = $dto->stockQuantity;
        $resource->trackInventory = $dto->trackInventory;
        $resource->allowBackorder = $dto->allowBackorder;
        $resource->isActive = $dto->isActive;
        $resource->isAvailable = $dto->isAvailable;
        /** @var list<array{url: string, position: int, isPrimary: bool}> $images */
        $images = array_values($dto->images);
        $resource->images = $images;

        return $resource;
    }
}
