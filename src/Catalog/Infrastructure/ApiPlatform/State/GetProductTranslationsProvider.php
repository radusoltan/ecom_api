<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * API Platform provider for getting product translations
 * Endpoint: GET /api/products/{id}/translations.
 */
final class GetProductTranslationsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get product ID from URI
        $productId = $uriVariables['id'] ?? null;
        if (!$productId) {
            throw new NotFoundHttpException('Product ID is required');
        }

        // Get current request
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No request available');
        }

        // Get tenant ID from headers
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            throw new NotFoundHttpException('X-Tenant-ID header is required');
        }

        // Find product
        $product = $this->productRepository->findByIdAndTenant(
            ProductId::fromString($productId),
            TenantId::fromString($tenantId)
        );

        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        // Build translations response
        $translations = [];

        // Add name translations
        if ($product->getNameTranslations()) {
            foreach ($product->getNameTranslations()->toArray() as $locale => $value) {
                if (!isset($translations[$locale])) {
                    $translations[$locale] = [];
                }
                $translations[$locale]['name'] = $value;
            }
        }

        // Add description translations
        if ($product->getDescriptionTranslations()) {
            foreach ($product->getDescriptionTranslations()->toArray() as $locale => $value) {
                if (!isset($translations[$locale])) {
                    $translations[$locale] = [];
                }
                $translations[$locale]['description'] = $value;
            }
        }

        // Add short description translations
        if ($product->getShortDescriptionTranslations()) {
            foreach ($product->getShortDescriptionTranslations()->toArray() as $locale => $value) {
                if (!isset($translations[$locale])) {
                    $translations[$locale] = [];
                }
                $translations[$locale]['shortDescription'] = $value;
            }
        }

        return [
            'productId' => $productId,
            'translations' => $translations,
        ];
    }
}
