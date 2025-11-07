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
 * Validate Coupon API Controller.
 *
 * Provides endpoint for validating coupon codes and calculating discounts
 * before order placement (used in shopping cart).
 */
#[AsController]
final readonly class ValidateCouponController
{
    public function __construct(
        private PromotionApplicationService $promotionService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/validate-coupon', name: 'api_validate_coupon', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            // Get tenant ID from header
            $tenantIdString = $request->headers->get('X-Tenant-ID');
            if (null === $tenantIdString) {
                return new JsonResponse([
                    'valid' => false,
                    'message' => 'Tenant ID is required',
                ], 400);
            }

            $tenantId = TenantId::fromString($tenantIdString);

            // Parse request body
            $data = json_decode($request->getContent(), true);
            if (null === $data) {
                return new JsonResponse([
                    'valid' => false,
                    'message' => 'Invalid JSON',
                ], 400);
            }

            $couponCode = $data['couponCode'] ?? null;
            $cartTotal = $data['cartTotal'] ?? null;
            $currency = $data['currency'] ?? 'USD';

            if (null === $couponCode || null === $cartTotal) {
                return new JsonResponse([
                    'valid' => false,
                    'message' => 'Coupon code and cart total are required',
                ], 400);
            }

            // Convert cart total to Money (already in minor units - cents)
            $subtotal = Money::fromScalars((int) $cartTotal, $currency);

            // Apply promotions with coupon code
            $result = $this->promotionService->applyPromotions(
                $tenantId,
                $subtotal,
                strtoupper($couponCode),
                []
            );

            // Check if coupon was applied
            $discount = $result['totalDiscount'];
            $wasApplied = null !== $discount && $discount->getAmount() > 0;

            if (!$wasApplied) {
                return new JsonResponse([
                    'valid' => false,
                    'message' => 'Coupon code is invalid or cannot be applied',
                ]);
            }

            // Calculate final total
            $finalPrice = $result['finalPrice'];

            // Get discount percentage if available
            $discountPercentage = null;
            $discountType = 'fixed';

            if (!empty($result['appliedPromotions'])) {
                // The appliedPromotions array contains arrays with structure:
                // ['promotionId', 'name', 'type', 'discountAmount', 'priceAfterDiscount']
                // Calculate the percentage from the discount amount
                $discountAmount = $discount->getAmount();
                $originalAmount = $subtotal->getAmount();

                if ($originalAmount > 0) {
                    $percentage = ($discountAmount / $originalAmount) * 100;
                    $discountPercentage = round($percentage, 2);
                    // If the percentage is a round number (10, 15, 25, etc.), it's likely a percentage discount
                    if ($percentage == round($percentage)) {
                        $discountType = 'percentage';
                    }
                }
            }

            return new JsonResponse([
                'valid' => true,
                'couponCode' => strtoupper($couponCode),
                'discountAmount' => $discount->getAmount(),
                'discountPercentage' => $discountPercentage,
                'discountType' => $discountType,
                'finalTotal' => $finalPrice->getAmount(),
                'currency' => $currency,
                'message' => 'Coupon applied successfully!',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error validating coupon', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'valid' => false,
                'message' => 'An error occurred while validating the coupon',
            ], 500);
        }
    }
}
