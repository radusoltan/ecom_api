<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Pricing\Domain\Model\PriceList;

/**
 * Data Transfer Object for PriceList.
 *
 * Used for API responses and query results
 */
final readonly class PriceListDTO
{
    /**
     * @param array<array<string, mixed>> $rules
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public int $priority,
        public array $rules,
        public ?string $validFrom,
        public ?string $validTo,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromDomainModel(PriceList $priceList): self
    {
        return new self(
            id: $priceList->id()->toString(),
            tenantId: $priceList->tenantId()->toString(),
            name: $priceList->name()->value(),
            priority: $priceList->priority(),
            rules: array_map(fn ($rule) => $rule->toArray(), $priceList->rules()),
            validFrom: $priceList->validFrom()?->format(\DateTimeImmutable::ATOM),
            validTo: $priceList->validTo()?->format(\DateTimeImmutable::ATOM),
            isActive: $priceList->isActive(),
            createdAt: $priceList->createdAt()->format(\DateTimeImmutable::ATOM),
            updatedAt: $priceList->updatedAt()->format(\DateTimeImmutable::ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'priority' => $this->priority,
            'rules' => $this->rules,
            'valid_from' => $this->validFrom,
            'valid_to' => $this->validTo,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
