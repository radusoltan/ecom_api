<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\PricingRule;
use App\Pricing\Domain\ValueObject\SegmentPricingRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine Entity for PriceList (Infrastructure Adapter).
 *
 * Converts between domain model and database representation
 */
#[ORM\Entity]
#[ORM\Table(name: 'price_lists')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_price_lists_tenant')]
#[ORM\Index(columns: ['is_active'], name: 'idx_price_lists_active')]
#[ORM\Index(columns: ['tenant_id', 'is_active'], name: 'idx_price_lists_tenant_active')]
class PriceListEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'uuid')]
    private string $tenantId;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::INTEGER)]
    private int $priority;

    #[ORM\Column(type: Types::JSON)]
    private array $rules = [];

    #[ORM\Column(type: Types::JSON)]
    private array $segmentRules = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * Convert from domain model to entity.
     */
    public static function fromDomainModel(PriceList $priceList): self
    {
        $entity = new self();
        $entity->id = $priceList->id()->toString();
        $entity->tenantId = $priceList->tenantId()->toString();
        $entity->name = $priceList->name()->value();
        $entity->priority = $priceList->priority();
        $entity->rules = array_map(fn ($rule) => $rule->toArray(), $priceList->rules());
        $entity->segmentRules = array_map(fn ($rule) => $rule->toArray(), $priceList->segmentRules());
        $entity->validFrom = $priceList->validFrom();
        $entity->validTo = $priceList->validTo();
        $entity->isActive = $priceList->isActive();
        $entity->createdAt = $priceList->createdAt();
        $entity->updatedAt = $priceList->updatedAt();

        return $entity;
    }

    /**
     * Update entity from domain model (for Doctrine 3.x compatibility).
     */
    public function updateFromDomainModel(PriceList $priceList): void
    {
        $this->name = $priceList->name()->value();
        $this->priority = $priceList->priority();
        $this->rules = array_map(fn ($rule) => $rule->toArray(), $priceList->rules());
        $this->segmentRules = array_map(fn ($rule) => $rule->toArray(), $priceList->segmentRules());
        $this->validFrom = $priceList->validFrom();
        $this->validTo = $priceList->validTo();
        $this->isActive = $priceList->isActive();
        $this->updatedAt = $priceList->updatedAt();
    }

    /**
     * Convert from entity to domain model.
     */
    public function toDomainModel(): PriceList
    {
        $rules = array_map(
            fn (array $ruleData) => $this->hydrateRule($ruleData),
            $this->rules
        );

        $segmentRules = array_map(
            fn (array $ruleData) => SegmentPricingRule::fromArray($ruleData),
            $this->segmentRules
        );

        return PriceList::reconstituteFromPersistence(
            PriceListId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            PriceListName::fromString($this->name),
            $this->priority,
            $rules,
            $segmentRules,
            $this->validFrom,
            $this->validTo,
            $this->isActive,
            $this->createdAt,
            $this->updatedAt
        );
    }

    /**
     * Hydrate PricingRule from array data.
     */
    private function hydrateRule(array $data): PricingRule
    {
        // Hydrate discount
        $discount = $this->hydrateDiscount($data['discount']);

        // Hydrate based on scope
        $scope = $data['scope'];

        if (PricingRule::SCOPE_PRODUCT === $scope) {
            return PricingRule::forProduct(
                ProductId::fromString($data['product_id']),
                $discount,
                $data['min_quantity'],
                $data['min_purchase_amount'] ? $this->hydrateMoney($data['min_purchase_amount']) : null
            );
        }

        if (PricingRule::SCOPE_CATEGORY === $scope) {
            return PricingRule::forCategory(
                $data['category_id'],
                $discount,
                $data['min_quantity'],
                $data['min_purchase_amount'] ? $this->hydrateMoney($data['min_purchase_amount']) : null
            );
        }

        // SCOPE_ALL
        return PricingRule::forAll(
            $discount,
            $data['min_quantity'],
            $data['min_purchase_amount'] ? $this->hydrateMoney($data['min_purchase_amount']) : null
        );
    }

    /**
     * Hydrate Discount from array data.
     */
    private function hydrateDiscount(array $data): Discount
    {
        if (Discount::TYPE_PERCENTAGE === $data['type']) {
            return Discount::percentage($data['percentage']);
        }

        return Discount::fixed($this->hydrateMoney($data['fixed_amount']));
    }

    /**
     * Hydrate Money from array data.
     */
    private function hydrateMoney(array $data): Money
    {
        return Money::fromScalars((int) $data['amount'], $data['currency']);
    }

    // Getters for Doctrine
    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
