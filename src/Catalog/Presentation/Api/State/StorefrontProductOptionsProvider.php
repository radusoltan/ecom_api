<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\Query\GetProductOptions;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Presentation\Api\Resource\StorefrontProductOptionResource;
use App\Catalog\Presentation\Api\Resource\StorefrontProductOptionValueResource;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Storefront state provider for GET /api/v1/products/{productId}/options.
 *
 * Returns [] for simple products (no catalog_configurable_products entry).
 * Returns 404 for non-existent product UUID.
 * RLS isolation enforced via app.tenant_id GUC (set by TenantRequestSubscriber).
 *
 * @implements ProviderInterface<StorefrontProductOptionResource>
 */
final readonly class StorefrontProductOptionsProvider implements ProviderInterface
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
     * @return list<StorefrontProductOptionResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenantId = $this->resolveTenantId();
        $productId = $this->resolveProductId($uriVariables);

        // Verify product exists under this tenant (RLS scoped) — 404 for non-existent, [] for simple
        $this->assertProductExists($productId, $tenantId);

        $query = new GetProductOptions(
            productId: ProductId::fromString($productId),
            tenantId: TenantId::fromString($tenantId),
        );

        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            return [];
        }

        /** @var \App\Catalog\Application\DTO\OptionDTO[] $optionDtos */
        $optionDtos = $stamp->getResult();

        $locale = $this->resolveLocale();

        return array_values(array_map(
            fn ($dto) => $this->toResource($dto, $locale),
            $optionDtos
        ));
    }

    /**
     * Resolve tenant ID from TenantContext (set by TenantRequestSubscriber from X-Tenant-ID header).
     * Falls back to raw header if context not yet hydrated (e.g. during test bootstrap).
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

    private function resolveLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $acceptLanguage = $request?->headers->get('Accept-Language') ?? 'en';
        $locale = explode(',', $acceptLanguage)[0];
        $locale = explode(';', $locale)[0];
        $locale = strtolower(trim($locale));
        $locale = explode('-', $locale)[0] ?? $locale;

        return '' !== $locale ? $locale : 'en';
    }

    private function toResource(\App\Catalog\Application\DTO\OptionDTO $dto, string $locale): StorefrontProductOptionResource
    {
        $resource = new StorefrontProductOptionResource();
        $resource->id = $dto->id;
        $resource->code = $dto->code;
        $resource->nameTranslations = $dto->nameTranslations;
        $resource->name = $this->localizedName($dto->nameTranslations, $locale);
        $resource->position = $dto->position;
        $resource->values = array_values(array_map(
            fn ($v) => $this->toValueResource($v, $locale),
            $dto->values
        ));

        return $resource;
    }

    private function toValueResource(\App\Catalog\Application\DTO\OptionValueDTO $dto, string $locale): StorefrontProductOptionValueResource
    {
        $resource = new StorefrontProductOptionValueResource();
        $resource->id = $dto->id;
        $resource->code = $dto->code;
        $resource->nameTranslations = $dto->nameTranslations;
        $resource->name = $this->localizedName($dto->nameTranslations, $locale);
        $resource->position = $dto->position;

        return $resource;
    }

    /**
     * @param array<string, string> $translations
     */
    private function localizedName(array $translations, string $locale): string
    {
        return $translations[$locale]
            ?? $translations[substr($locale, 0, 2)]
            ?? $translations['en']
            ?? (array_values($translations)[0] ?? '');
    }
}
