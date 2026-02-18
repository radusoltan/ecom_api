<?php

declare(strict_types=1);

namespace App\Tax\Application\DTO;

use App\Tax\Domain\Model\TaxRule;

/**
 * Tax Rule Data Transfer Object.
 *
 * Read-only DTO for tax rule data exposed via Application layer.
 * Maps all TaxRule aggregate fields to primitive types for API responses.
 */
final readonly class TaxRuleDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $countryCode,
        public ?string $regionCode,
        public string $category,
        public float $ratePercentage,
        public string $name,
        public ?string $description,
        public int $priority,
        public bool $isActive,
        public string $validFrom,
        public ?string $validTo,
        public bool $isReverseCharge,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * Create DTO from domain model.
     */
    public static function fromDomainModel(TaxRule $taxRule): self
    {
        return new self(
            id: $taxRule->id()->toString(),
            tenantId: $taxRule->tenantId()->toString(),
            countryCode: $taxRule->jurisdiction()->countryCode(),
            regionCode: $taxRule->jurisdiction()->regionCode(),
            category: $taxRule->category()->value,
            ratePercentage: $taxRule->rate()->percentage(),
            name: $taxRule->name(),
            description: $taxRule->description(),
            priority: $taxRule->priority(),
            isActive: $taxRule->isActive(),
            validFrom: $taxRule->validFrom()->format('c'),
            validTo: $taxRule->validTo()?->format('c'),
            isReverseCharge: $taxRule->isReverseCharge(),
            createdAt: $taxRule->createdAt()->format('c'),
            updatedAt: $taxRule->updatedAt()->format('c')
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenantId'],
            countryCode: $data['countryCode'],
            regionCode: $data['regionCode'] ?? null,
            category: $data['category'],
            ratePercentage: $data['ratePercentage'],
            name: $data['name'],
            description: $data['description'] ?? null,
            priority: $data['priority'],
            isActive: $data['isActive'],
            validFrom: $data['validFrom'],
            validTo: $data['validTo'] ?? null,
            isReverseCharge: $data['isReverseCharge'],
            createdAt: $data['createdAt'],
            updatedAt: $data['updatedAt']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'countryCode' => $this->countryCode,
            'regionCode' => $this->regionCode,
            'category' => $this->category,
            'ratePercentage' => $this->ratePercentage,
            'name' => $this->name,
            'description' => $this->description,
            'priority' => $this->priority,
            'isActive' => $this->isActive,
            'validFrom' => $this->validFrom,
            'validTo' => $this->validTo,
            'isReverseCharge' => $this->isReverseCharge,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
