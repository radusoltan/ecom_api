<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Controller;

use App\Catalog\Application\Command\UpdateProductTranslations;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Controller for product translation endpoints.
 */
final class ProductTranslationController extends AbstractController
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    /**
     * GET /api/products/{id}/translations.
     */
    public function getTranslations(string $id, Request $request): JsonResponse
    {
        // Get tenant ID from headers
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Find product
            $product = $this->productRepository->findByIdAndTenant(
                ProductId::fromString($id),
                TenantId::fromString($tenantId)
            );

            if (!$product) {
                return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
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

            return $this->json([
                'productId' => $id,
                'translations' => $translations,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PATCH /api/products/{id}/translations.
     */
    public function updateTranslations(string $id, Request $request): JsonResponse
    {
        // Get tenant ID from headers
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        // Parse request body
        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        // Validate locale
        $locale = $data['locale'] ?? null;
        if (!$locale) {
            return $this->json(['error' => 'Locale is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Create and dispatch command
            $command = new UpdateProductTranslations(
                productId: ProductId::fromString($id),
                tenantId: TenantId::fromString($tenantId),
                locale: Locale::fromString($locale),
                name: $data['name'] ?? null,
                description: $data['description'] ?? null,
                shortDescription: $data['shortDescription'] ?? null
            );

            $this->messageBus->dispatch($command);

            return $this->json([
                'message' => 'Translations updated successfully',
                'productId' => $id,
                'locale' => $locale,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
