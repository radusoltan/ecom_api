<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\Query\SearchCustomers\SearchCustomersQuery;
use App\Customer\Application\Query\SearchCustomers\SearchCustomersQueryHandler;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Customer Collection Provider with Search & Filtering.
 *
 * @implements ProviderInterface<CustomerEntity>
 */
final readonly class CustomerCollectionProvider implements ProviderInterface
{
    public function __construct(
        private SearchCustomersQueryHandler $searchHandler,
        private RequestStack $requestStack
    ) {
    }

    /**
     * @return CustomerEntity[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        // Get tenant ID from context or header
        $tenantId = $context['tenant_id'] ?? $request->headers->get('X-Tenant-ID');
        if (null === $tenantId) {
            throw new \InvalidArgumentException('Tenant ID is required (X-Tenant-ID header or context)');
        }

        // Extract all search/filter parameters
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

        $result = ($this->searchHandler)($query);

        // Convert CustomerSummaryDTOs to entities for API Platform
        return array_map(
            fn ($dto) => $this->populateEntityFromDTO(new CustomerEntity(), $dto),
            $result->items
        );
    }

    /**
     * Populate entity from CustomerSummaryDTO for API response.
     *
     * @param mixed $dto CustomerSummaryDTO
     */
    private function populateEntityFromDTO(CustomerEntity $entity, mixed $dto): CustomerEntity
    {
        $entity->setId($dto->id);
        $entity->setTenantId($dto->tenantId);
        $entity->setEmail($dto->email);
        $entity->setFirstName($dto->firstName);
        $entity->setLastName($dto->lastName);
        $entity->setPhoneNumber($dto->phoneNumber);
        $entity->setSegment($dto->segment);
        $entity->setLoyaltyPoints($dto->loyaltyPoints);
        $entity->setIsActive($dto->isActive);
        $entity->setCreatedAt($dto->createdAt);
        $entity->setUpdatedAt($dto->updatedAt);

        return $entity;
    }
}
