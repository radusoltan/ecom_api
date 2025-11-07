<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Controller;

use App\Catalog\Application\Command\GenerateVariants;
use App\Catalog\Application\Command\UpdateVariant;
use App\Catalog\Application\Query\GetVariantsByProduct;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for product variants management.
 */
final class ProductVariantsController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus
    ) {
    }

    /**
     * Get variants for a product with optional filters
     * GET /api/products/{id}/variants.
     */
    #[Route('/api/products/{id}/variants', methods: ['GET'])]
    public function getVariants(string $id, Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Parse filters from query params (e.g., ?option[color]=red&option[size]=xl)
            $filters = [];
            foreach ($request->query->all() as $key => $value) {
                if (str_starts_with($key, 'option[')) {
                    $optionCode = substr($key, 7, -1);
                    $filters[$optionCode] = $value;
                }
            }

            $query = new GetVariantsByProduct(
                ProductId::fromString($id),
                TenantId::fromString($tenantId),
                $filters,
                $request->query->getBoolean('activeOnly'),
                $request->query->getInt('page', 1),
                $request->query->getInt('limit', 50)
            );

            $envelope = $this->queryBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);
            $result = $handledStamp->getResult();

            return $this->json($result);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate variants for a product
     * POST /api/products/{id}/variants:generate.
     */
    #[Route('/api/products/{id}/variants:generate', methods: ['POST'])]
    public function generateVariants(string $id, Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $defaultPrice = null;
            if (isset($data['defaultPrice'])) {
                $defaultPrice = Money::fromScalars(
                    $data['defaultPrice']['amount'],
                    $data['defaultPrice']['currency'] ?? 'USD'
                );
            }

            $command = new GenerateVariants(
                ProductId::fromString($id),
                TenantId::fromString($tenantId),
                $defaultPrice,
                $data['defaultStock'] ?? 0,
                $data['activateByDefault'] ?? true,
                $data['skuBase'] ?? null
            );

            $this->commandBus->dispatch($command);

            return $this->json(
                ['message' => 'Variants generated successfully'],
                Response::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a specific variant
     * PATCH /api/variants/{id}.
     */
    #[Route('/api/variants/{id}', methods: ['PATCH'])]
    public function updateVariant(string $id, Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        // Product ID is required for variant update
        if (!isset($data['productId'])) {
            return $this->json(['error' => 'productId is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $price = null;
            if (isset($data['price'])) {
                $price = Money::fromScalars(
                    $data['price']['amount'],
                    $data['price']['currency'] ?? 'USD'
                );
            }

            $command = new UpdateVariant(
                VariantId::fromString($id),
                ProductId::fromString($data['productId']),
                TenantId::fromString($tenantId),
                $price,
                $data['stockQuantity'] ?? null,
                $data['isActive'] ?? null,
                $data['trackInventory'] ?? null,
                $data['allowBackorder'] ?? null,
                $data['images'] ?? null
            );

            $this->commandBus->dispatch($command);

            return $this->json(['message' => 'Variant updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
