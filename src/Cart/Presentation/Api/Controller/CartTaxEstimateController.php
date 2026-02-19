<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Controller;

use App\Cart\Application\Query\GetCartTaxEstimate\GetCartTaxEstimateQuery;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class CartTaxEstimateController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    #[Route('/api/carts/{cartId}/tax-estimate', name: 'api_cart_tax_estimate', methods: ['GET'])]
    public function __invoke(Request $request, string $cartId): JsonResponse
    {
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            return new JsonResponse(
                ['error' => 'Tenant context not set. Ensure X-Tenant-ID header is provided.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Extract optional query parameters
        $countryCode = $request->query->get('countryCode');
        $regionCode = $request->query->get('regionCode');

        // Validate country code format if provided
        if (null !== $countryCode) {
            $countryCode = strtoupper($countryCode);
            if (1 !== preg_match('/^[A-Z]{2}$/', $countryCode)) {
                return new JsonResponse(
                    ['error' => 'countryCode must be a 2-letter ISO 3166-1 alpha-2 code'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        if (null !== $regionCode) {
            $regionCode = strtoupper($regionCode);
            if (1 !== preg_match('/^[A-Z0-9]{2,3}$/', $regionCode)) {
                return new JsonResponse(
                    ['error' => 'regionCode must be 2-3 alphanumeric characters'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        try {
            $query = new GetCartTaxEstimateQuery(
                cartId: $cartId,
                tenantId: $tenantId->toString(),
                countryCode: $countryCode,
                regionCode: $regionCode,
            );

            $envelope = $this->queryBus->dispatch($query);
            $stamp = $envelope->last(HandledStamp::class);

            if (!$stamp instanceof HandledStamp) {
                return new JsonResponse(
                    ['error' => 'No handler found for tax estimate query'],
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                );
            }

            $result = $stamp->getResult();

            return new JsonResponse($result->toArray());
        } catch (CartNotFoundException) {
            return new JsonResponse(
                ['error' => sprintf('Cart "%s" not found', $cartId)],
                Response::HTTP_NOT_FOUND,
            );
        } catch (\DomainException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST,
            );
        }
    }
}
