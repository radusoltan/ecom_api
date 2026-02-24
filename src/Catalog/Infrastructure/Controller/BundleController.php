<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Controller;

use App\Catalog\Application\Command\CreateBundleCommand;
use App\Catalog\Application\Command\RemoveBundleCommand;
use App\Catalog\Application\Command\UpdateBundleCommand;
use App\Catalog\Application\Query\GetBundleItemsQuery;
use App\Catalog\Domain\Model\ProductId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * BundleController.
 *
 * REST API endpoints for product bundle management.
 */
#[Route('/api/v1/products/{productId}/bundle', name: 'api_bundle_')]
final class BundleController extends AbstractController
{
    use HandleTrait;

    public function __construct(
        /** @phpstan-ignore-next-line property.onlyWritten (used via HandleTrait) */
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $commandBus;
    }

    /**
     * GET /api/products/{productId}/bundle
     * Get bundle configuration for a product.
     */
    #[Route('', name: 'get', methods: ['GET'])]
    public function get(string $productId): JsonResponse
    {
        try {
            $query = new GetBundleItemsQuery(ProductId::fromString($productId));
            $result = $this->queryBus->dispatch($query);

            // Get the result from the query handler
            $bundle = $result->last(\Symfony\Component\Messenger\Stamp\HandledStamp::class)?->getResult();

            if (null === $bundle) {
                return new JsonResponse(
                    ['error' => 'Bundle not found for this product'],
                    Response::HTTP_NOT_FOUND
                );
            }

            return new JsonResponse($bundle, Response::HTTP_OK);
        } catch (\DomainException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * POST /api/products/{productId}/bundle
     * Create bundle configuration for a product.
     *
     * Body:
     * {
     *   "items": [
     *     {"productId": "uuid", "quantity": 2, "price": {"amount": 1000, "currency": "USD"}},
     *     ...
     *   ],
     *   "discountPercentage": 10.5
     * }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(string $productId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['items']) || !is_array($data['items'])) {
                return new JsonResponse(
                    ['error' => 'Items array is required'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $discountPercentage = $data['discountPercentage'] ?? 0.0;

            $command = new CreateBundleCommand(
                ProductId::fromString($productId),
                $data['items'],
                (float) $discountPercentage
            );

            $this->handle($command);

            return new JsonResponse(
                ['message' => 'Bundle created successfully'],
                Response::HTTP_CREATED
            );
        } catch (\DomainException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Internal server error: '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * PUT /api/products/{productId}/bundle
     * Update existing bundle configuration.
     *
     * Body: same as POST
     */
    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(string $productId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['items']) || !is_array($data['items'])) {
                return new JsonResponse(
                    ['error' => 'Items array is required'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $discountPercentage = $data['discountPercentage'] ?? 0.0;

            $command = new UpdateBundleCommand(
                ProductId::fromString($productId),
                $data['items'],
                (float) $discountPercentage
            );

            $this->handle($command);

            return new JsonResponse(
                ['message' => 'Bundle updated successfully'],
                Response::HTTP_OK
            );
        } catch (\DomainException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Internal server error: '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * DELETE /api/products/{productId}/bundle
     * Remove bundle configuration from a product.
     */
    #[Route('', name: 'delete', methods: ['DELETE'])]
    public function delete(string $productId): JsonResponse
    {
        try {
            $command = new RemoveBundleCommand(ProductId::fromString($productId));

            $this->handle($command);

            return new JsonResponse(
                ['message' => 'Bundle removed successfully'],
                Response::HTTP_OK
            );
        } catch (\DomainException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
