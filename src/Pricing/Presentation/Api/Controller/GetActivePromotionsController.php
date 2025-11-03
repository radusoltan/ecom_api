<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Controller;

use App\Pricing\Application\Query\GetActivePromotions\GetActivePromotionsQuery;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Get Active Promotions API Controller
 *
 * Returns only currently active promotions for the current tenant.
 */
#[AsController]
final readonly class GetActivePromotionsController
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/api/v1/active-promotions', name: 'api_get_active_promotions', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Get tenant ID from header
        $tenantIdString = $request->headers->get('X-Tenant-ID');
        if ($tenantIdString === null) {
            return new JsonResponse([
                'error' => 'Tenant ID is required',
            ], 400);
        }

        $tenantId = TenantId::fromString($tenantIdString);

        // Dispatch query
        $envelope = $this->messageBus->dispatch(new GetActivePromotionsQuery($tenantId));

        $handledStamp = $envelope->last(HandledStamp::class);
        $dtos = $handledStamp?->getResult() ?? [];

        // Convert DTOs to arrays for JSON serialization
        $result = array_map(function ($dto) {
            return [
                'id' => $dto->id,
                'tenantId' => $dto->tenantId,
                'name' => $dto->name,
                'type' => $dto->type,
                'discountType' => $dto->discountType,
                'discountValue' => $dto->discountValue,
                'priority' => $dto->priority,
                'active' => $dto->isActive,
                'couponCode' => $dto->couponCode,
                'conditions' => $dto->conditions,
                'validFrom' => $dto->validFrom,
                'validTo' => $dto->validTo,
                'createdAt' => $dto->createdAt,
                'updatedAt' => $dto->updatedAt,
            ];
        }, $dtos);

        // Return as JSON array
        return new JsonResponse($result);
    }
}
