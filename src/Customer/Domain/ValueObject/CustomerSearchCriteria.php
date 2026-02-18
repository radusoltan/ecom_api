<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Customer Search Criteria Value Object.
 *
 * Encapsulates all search parameters for customer queries.
 */
final readonly class CustomerSearchCriteria
{
    public function __construct(
        public TenantId $tenantId,
        public ?string $searchTerm = null,
        public ?string $status = null,
        public ?string $segment = null,
        public ?\DateTimeImmutable $registeredFrom = null,
        public ?\DateTimeImmutable $registeredTo = null,
        public ?bool $hasOrders = null,
        public ?int $minOrderCount = null,
        public ?int $maxOrderCount = null,
        public string $sortBy = 'createdAt',
        public string $sortOrder = 'DESC',
        public int $page = 1,
        public int $limit = 20,
    ) {
        $this->validatePagination();
        $this->validateSorting();
        $this->validateDateRange();
    }

    private function validatePagination(): void
    {
        if ($this->page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        if ($this->limit < 1) {
            throw new \InvalidArgumentException('Limit must be greater than 0');
        }

        if ($this->limit > 100) {
            throw new \InvalidArgumentException('Limit cannot exceed 100');
        }
    }

    private function validateSorting(): void
    {
        $allowedSortFields = ['createdAt', 'updatedAt', 'firstName', 'lastName', 'email', 'loyaltyPoints'];
        if (!in_array($this->sortBy, $allowedSortFields, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid sort field: "%s". Allowed: %s', $this->sortBy, implode(', ', $allowedSortFields)));
        }

        $allowedSortOrders = ['ASC', 'DESC'];
        if (!in_array($this->sortOrder, $allowedSortOrders, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid sort order: "%s". Allowed: %s', $this->sortOrder, implode(', ', $allowedSortOrders)));
        }
    }

    private function validateDateRange(): void
    {
        if (null !== $this->registeredFrom && null !== $this->registeredTo) {
            if ($this->registeredFrom > $this->registeredTo) {
                throw new \InvalidArgumentException('registeredFrom cannot be after registeredTo');
            }
        }
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
