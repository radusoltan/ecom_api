<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Pricing\Application\Query\GetDiscountUsage\GetDiscountUsageQuery;
use App\Pricing\Application\Query\GetDiscountUsage\GetDiscountUsageQueryHandler;
use App\Pricing\Presentation\Api\Resource\DiscountUsageResource;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * State provider for discount usage analytics.
 *
 * @implements ProviderInterface<DiscountUsageResource>
 */
final readonly class DiscountUsageProvider implements ProviderInterface
{
    public function __construct(
        private GetDiscountUsageQueryHandler $queryHandler,
        private TenantContext $tenantContext,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DiscountUsageResource
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return new DiscountUsageResource([], [], []);
        }

        // Parse date range from query parameters
        $dateRange = $this->parseDateRange($request);

        // Get top limit from query parameters
        $topLimit = (int) $request->query->get('top_limit', 10);
        $topLimit = max(1, min($topLimit, 50)); // Clamp between 1-50

        // Create and execute query
        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantContext->getCurrentTenantId(),
            dateRange: $dateRange,
            topCouponsLimit: $topLimit
        );

        $dto = ($this->queryHandler)($query);
        $data = $dto->toArray();

        return new DiscountUsageResource(
            summary: $data['summary'],
            topCoupons: $data['topCoupons'],
            unusedPromotions: $data['unusedPromotions']
        );
    }

    private function parseDateRange(\Symfony\Component\HttpFoundation\Request $request): DateRangeFilter
    {
        // Check for custom date range
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if (null !== $startDate && null !== $endDate) {
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
