<?php

declare(strict_types=1);

namespace App\Tax\Application\DTO;

/**
 * Tax Rule Data Transfer Object
 *
 * Read-only DTO for tax rule data.
 */
final readonly class TaxRuleDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public string $countryCode,
        public ?string $regionCode,
        public float $ratePercentage,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenantId'],
            name: $data['name'],
            countryCode: $data['countryCode'],
            regionCode: $data['regionCode'] ?? null,
            ratePercentage: $data['ratePercentage'],
            isActive: $data['isActive'],
            createdAt: $data['createdAt'],
            updatedAt: $data['updatedAt']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'countryCode' => $this->countryCode,
            'regionCode' => $this->regionCode,
            'ratePercentage' => $this->ratePercentage,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
