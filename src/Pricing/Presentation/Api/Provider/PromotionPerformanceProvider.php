<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Pricing\Application\Query\GetPromotionPerformance\GetPromotionPerformanceQuery;
use App\Pricing\Application\Query\GetPromotionPerformance\GetPromotionPerformanceQueryHandler;
use App\Pricing\Presentation\Api\Resource\PromotionPerformanceResource;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * State provider for promotion performance analytics.
 *
 * @implements ProviderInterface<PromotionPerformanceResource>
 */
final readonly class PromotionPerformanceProvider implements ProviderInterface
{
    public function __construct(
        private GetPromotionPerformanceQueryHandler $queryHandler,
        private TenantContext $tenantContext,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return [];
        }

        // Parse date range from query parameters
        $dateRange = $this->parseDateRange($request);

        // Get limit from query parameters
        $limit = (int) ($request->query->get('limit', 10));
        $limit = max(1, min($limit, 100)); // Clamp between 1-100

        // Create and execute query
        $query = new GetPromotionPerformanceQuery(
            tenantId: $this->tenantContext->getCurrentTenantId(),
            dateRange: $dateRange,
            promotionId: $request->query->get('promotion_id'),
            limit: $limit
        );

        $results = ($this->queryHandler)($query);

        // Convert DTOs to resources
        return array_map(
            fn ($dto) => new PromotionPerformanceResource(
                promotionId: $dto->promotionId,
                promotionName: $dto->promotionName,
                promotionType: $dto->promotionType,
                ordersCount: $dto->ordersCount,
                totalRevenue: $dto->toArray()['totalRevenue'],
                totalDiscount: $dto->toArray()['totalDiscount'],
                averageDiscountAmount: $dto->averageDiscountAmount,
                conversionRate: $dto->conversionRate,
                validFrom: $dto->validFrom?->format(\DateTimeInterface::ATOM),
                validTo: $dto->validTo?->format(\DateTimeInterface::ATOM),
            ),
            $results
        );
    }

    private function parseDateRange(\Symfony\Component\HttpFoundation\Request $request): DateRangeFilter
    {
        // Check for custom date range
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if ($startDate !== null && $endDate !== null) {
            try {
                return DateRangeFilter::fromDates(
                    new \DateTimeImmutable($startDate, new \DateTimeZone('UTC')),
                    new \DateTimeImmutable($endDate, new \DateTimeZone('UTC'))
                );
            } catch (\Exception) {
                // Fall back to preset if dates are invalid
            }
        }

        // Use preset
        $preset = $request->query->get('period', 'last_30_days');

        try {
            return DateRangeFilter::fromPreset($preset);
        } catch (\InvalidArgumentException) {
            return DateRangeFilter::default();
        }
    }
}
