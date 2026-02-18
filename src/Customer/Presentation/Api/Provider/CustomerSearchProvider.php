<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\Query\SearchCustomers\SearchCustomersQuery;
use App\Customer\Application\Query\SearchCustomers\SearchCustomersQueryHandler;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Customer Search Provider.
 *
 * Provides customer collection with search and filtering capabilities.
 *
 * @implements ProviderInterface<object>
 */
final readonly class CustomerSearchProvider implements ProviderInterface
{
    public function __construct(
        private SearchCustomersQueryHandler $handler,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        // Get tenant ID from header
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (null === $tenantId) {
            throw new \InvalidArgumentException('X-Tenant-ID header is required');
        }

        // Extract query parameters
        $searchTerm = $request->query->get('search');
        $status = $request->query->get('status');
        $segment = $request->query->get('segment');
        $registeredFrom = $request->query->get('registeredFrom');
        $registeredTo = $request->query->get('registeredTo');
        $hasOrders = $request->query->get('hasOrders');
        $minOrderCount = $request->query->get('minOrderCount');
        $maxOrderCount = $request->query->get('maxOrderCount');

        $sortByParam = $request->query->get('sortBy', 'createdAt');
        $sortBy = is_string($sortByParam) ? $sortByParam : 'createdAt';

        $sortOrderParam = $request->query->get('sortOrder', 'DESC');
        $sortOrder = is_string($sortOrderParam) ? strtoupper($sortOrderParam) : 'DESC';

        $page = (int) $request->query->get('page', 1);
        $limit = min((int) $request->query->get('limit', 20), 100);

        $query = new SearchCustomersQuery(
            tenantId: $tenantId,
            searchTerm: is_string($searchTerm) ? $searchTerm : null,
            status: is_string($status) ? $status : null,
            segment: is_string($segment) ? $segment : null,
            registeredFrom: is_string($registeredFrom) ? new \DateTimeImmutable($registeredFrom) : null,
            registeredTo: is_string($registeredTo) ? new \DateTimeImmutable($registeredTo) : null,
            hasOrders: null !== $hasOrders ? filter_var($hasOrders, FILTER_VALIDATE_BOOLEAN) : null,
            minOrderCount: null !== $minOrderCount ? (int) $minOrderCount : null,
            maxOrderCount: null !== $maxOrderCount ? (int) $maxOrderCount : null,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
            page: $page,
            limit: $limit
        );

        $result = ($this->handler)($query);

        // Return items (CustomerSummaryDTO objects)
        return $result->items;
    }
}
