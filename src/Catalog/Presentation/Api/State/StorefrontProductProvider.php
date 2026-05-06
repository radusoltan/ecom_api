<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\DTO\StorefrontProductDto;
use App\Catalog\Infrastructure\Persistence\Doctrine\ReadModel\ProductListingReadRepository;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<StorefrontProductDto>
 */
final class StorefrontProductProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProductListingReadRepository $readRepository,
        private readonly TenantContext $tenantContext,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StorefrontProductDto
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new NotFoundHttpException();
        }

        $tenantId = $this->tenantContext->getTenantId();
        if (null === $tenantId && $request->headers->has('X-Tenant-ID')) {
            $tenantId = $request->headers->get('X-Tenant-ID');
        }

        if (null === $tenantId || '' === $tenantId) {
            throw new NotFoundHttpException();
        }

        $slug = $uriVariables['slug'] ?? null;
        if (!is_string($slug) || '' === $slug) {
            throw new NotFoundHttpException();
        }

        $acceptLanguage = $request->headers->get('Accept-Language') ?? 'en';
        $locale = $this->parseLocale($acceptLanguage);

        $dto = $this->readRepository->findOneBySlugForStorefront($slug, $locale);
        if (null === $dto) {
            throw new NotFoundHttpException();
        }

        return $dto;
    }

    private function parseLocale(string $acceptLanguage): string
    {
        $locales = explode(',', $acceptLanguage);
        if ([] === $locales) {
            return 'en';
        }

        $locale = explode(';', $locales[0])[0];
        $locale = strtolower(trim($locale));
        $locale = explode('-', $locale)[0] ?? $locale;

        return '' !== $locale ? $locale : 'en';
    }
}
