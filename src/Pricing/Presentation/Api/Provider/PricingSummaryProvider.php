<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Pricing\Application\Query\GetPricingSummary\GetPricingSummaryQuery;
use App\Pricing\Application\Query\GetPricingSummary\GetPricingSummaryQueryHandler;
use App\Pricing\Presentation\Api\Resource\PricingSummaryResource;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * State provider for overall pricing analytics summary.
 *
 * @implements ProviderInterface<PricingSummaryResource>
 */
final readonly class PricingSummaryProvider implements ProviderInterface
{
    public function __construct(
        private GetPricingSummaryQueryHandler $queryHandler,
        private TenantContext $tenantContext,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PricingSummaryResource
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return new PricingSummaryResource([], [], []);
        }

        // Parse date range from query parameters
        $dateRange = $this->parseDateRange($request);

        // Get top promotions limit from query parameters
        $topPromotionsLimit = (int) $request->query->get('top_promotions_limit', 5);
        $topPromotionsLimit = max(1, min($topPromotionsLimit, 20)); // Clamp between 1-20

        // Create and execute query
        $query = new GetPricingSummaryQuery(
            tenantId: $this->tenantContext->getCurrentTenantId(),
            dateRange: $dateRange,
            topPromotionsLimit: $topPromotionsLimit
        );

        $dto = ($this->queryHandler)($query);
        $data = $dto->toArray();

        return new PricingSummaryResource(
            period: $data['period'],
            summary: $data['summary'],
            topPromotions: $data['topPromotions']
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
