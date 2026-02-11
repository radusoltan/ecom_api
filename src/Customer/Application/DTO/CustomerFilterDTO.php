<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Customer Filter DTO.
 *
 * DTO for customer filtering from API requests.
 */
final readonly class CustomerFilterDTO
{
    public function __construct(
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
        public int $limit = 20
    ) {
    }

    /**
     * Create from request array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            searchTerm: isset($data['search']) ? (string) $data['search'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            segment: isset($data['segment']) ? (string) $data['segment'] : null,
            registeredFrom: isset($data['registeredFrom']) ? new \DateTimeImmutable($data['registeredFrom']) : null,
            registeredTo: isset($data['registeredTo']) ? new \DateTimeImmutable($data['registeredTo']) : null,
            hasOrders: isset($data['hasOrders']) ? filter_var($data['hasOrders'], FILTER_VALIDATE_BOOLEAN) : null,
            minOrderCount: isset($data['minOrderCount']) ? (int) $data['minOrderCount'] : null,
            maxOrderCount: isset($data['maxOrderCount']) ? (int) $data['maxOrderCount'] : null,
            sortBy: $data['sortBy'] ?? 'createdAt',
            sortOrder: strtoupper($data['sortOrder'] ?? 'DESC'),
            page: isset($data['page']) ? (int) $data['page'] : 1,
            limit: isset($data['limit']) ? min((int) $data['limit'], 100) : 20
        );
    }
}
