<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Controller;

use App\Pricing\Application\Service\PromotionApplicationService;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Preview Promotion API Controller.
 *
 * Provides endpoint for previewing how promotions will be applied to a cart
 * before actual order placement. Used for admin UI to test promotions.
 */
#[AsController]
final readonly class PreviewPromotionController
{
    public function __construct(
        private PromotionApplicationService $promotionService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/promotions/preview', name: 'api_promotion_preview', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            // Get tenant ID from header
            $tenantIdString = $request->headers->get('X-Tenant-ID');
            if (null === $tenantIdString) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Tenant ID is required',
                ], 400);
            }

            $tenantId = TenantId::fromString($tenantIdString);

            // Parse request body
            $data = json_decode($request->getContent(), true);
            if (null === $data) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid JSON',
                ], 400);
            }

            // Extract parameters
            $subtotal = $data['subtotal'] ?? null;
            $currency = $data['currency'] ?? 'USD';
            $couponCode = $data['couponCode'] ?? null;
            $productIds = $data['productIds'] ?? [];

            if (null === $subtotal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Subtotal is required',
                ], 400);
            }

            // Convert subtotal to Money (already in minor units - cents)
            $subtotalMoney = Money::fromScalars((int) $subtotal, $currency);

            // Apply promotions
            $result = $this->promotionService->applyPromotions(
                $tenantId,
                $subtotalMoney,
                null !== $couponCode ? strtoupper($couponCode) : null,
                $productIds
            );

            // Format response
            $discount = $result['totalDiscount'];
            $finalPrice = $result['finalPrice'];
            $appliedPromotions = $result['appliedPromotions'];

            return new JsonResponse([
                'success' => true,
                'preview' => [
                    'originalSubtotal' => $subtotalMoney->getAmount(),
                    'totalDiscount' => null !== $discount ? $discount->getAmount() : 0,
                    'finalPrice' => $finalPrice->getAmount(),
                    'currency' => $currency,
                    'appliedPromotions' => array_map(function ($promotion) {
                        return [
                            'promotionId' => $promotion[0],
                            'name' => $promotion[1],
                            'type' => $promotion[2],
                            'discountAmount' => $promotion[3],
                            'priceAfterDiscount' => $promotion[4],
                        ];
                    }, $appliedPromotions),
                    'promotionsCount' => count($appliedPromotions),
                    'savingsPercentage' => $subtotalMoney->getAmount() > 0
                        ? round((null !== $discount ? $discount->getAmount() : 0) / $subtotalMoney->getAmount() * 100, 2)
                        : 0,
                ],
                'message' => count($appliedPromotions) > 0
                    ? sprintf('Successfully applied %d promotion(s)', count($appliedPromotions))
                    : 'No promotions applicable',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error previewing promotion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred while previewing the promotion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
