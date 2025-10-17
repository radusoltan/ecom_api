<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Controller;

use App\Catalog\Application\Command\UpdateCategoryTranslations;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Controller for category translation endpoints
 */
final class CategoryTranslationController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly MessageBusInterface $messageBus
    ) {}

    /**
     * GET /api/categories/{id}/translations
     */
    public function getTranslations(string $id, Request $request): JsonResponse
    {
        // Get tenant ID from headers
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Find category
            $category = $this->categoryRepository->findByIdAndTenant(
                CategoryId::fromString($id),
                TenantId::fromString($tenantId)
            );

            if (!$category) {
                return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
            }

            // Build translations response
            $translations = [];

            // Add name translations
            if ($category->getNameTranslations()) {
                foreach ($category->getNameTranslations()->toArray() as $locale => $value) {
                    if (!isset($translations[$locale])) {
                        $translations[$locale] = [];
                    }
                    $translations[$locale]['name'] = $value;
                }
            }

            // Add description translations
            if ($category->getDescriptionTranslations()) {
                foreach ($category->getDescriptionTranslations()->toArray() as $locale => $value) {
                    if (!isset($translations[$locale])) {
                        $translations[$locale] = [];
                    }
                    $translations[$locale]['description'] = $value;
                }
            }

            return $this->json([
                'categoryId' => $id,
                'translations' => $translations,
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PATCH /api/categories/{id}/translations
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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        // Validate locale
        $locale = $data['locale'] ?? null;
        if (!$locale) {
            return $this->json(['error' => 'Locale is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Create and dispatch command
            $command = new UpdateCategoryTranslations(
                categoryId: CategoryId::fromString($id),
                tenantId: TenantId::fromString($tenantId),
                locale: Locale::fromString($locale),
                name: $data['name'] ?? null,
                description: $data['description'] ?? null
            );

            $this->messageBus->dispatch($command);

            return $this->json([
                'message' => 'Translations updated successfully',
                'categoryId' => $id,
                'locale' => $locale,
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}