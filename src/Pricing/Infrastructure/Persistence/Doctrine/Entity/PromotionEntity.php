<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Pricing\Presentation\Api\Processor\ActivatePromotionProcessor;
use App\Pricing\Presentation\Api\Processor\CreatePromotionProcessor;
use App\Pricing\Presentation\Api\Processor\DeactivatePromotionProcessor;
use App\Pricing\Presentation\Api\Processor\UpdatePromotionProcessor;
use App\Pricing\Presentation\Api\Provider\PromotionCollectionProvider;
use App\Pricing\Presentation\Api\Provider\PromotionItemProvider;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'promotions')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_promotions_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_promotions_type', columns: ['type'])]
#[ORM\Index(name: 'idx_promotions_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_promotions_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_promotions_coupon_code', columns: ['coupon_code'])]
#[ORM\Index(name: 'idx_promotions_tenant_active', columns: ['tenant_id', 'is_active'])]
#[ORM\Index(name: 'idx_promotions_tenant_type', columns: ['tenant_id', 'type'])]
#[ORM\Index(name: 'idx_promotions_created_at', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'unique_coupon_per_tenant', columns: ['tenant_id', 'coupon_code'])]
#[ApiResource(
    shortName: 'Promotion',
    operations: [
        new Get(provider: PromotionItemProvider::class),
        new GetCollection(provider: PromotionCollectionProvider::class),
        new Post(processor: CreatePromotionProcessor::class),
        new Put(processor: UpdatePromotionProcessor::class),
        new Patch(
            uriTemplate: '/promotions/{id}/activate',
            processor: ActivatePromotionProcessor::class
        ),
        new Patch(
            uriTemplate: '/promotions/{id}/deactivate',
            processor: DeactivatePromotionProcessor::class
        ),
        new Delete(),
    ]
)]
class PromotionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $type;

    #[ORM\Column(type: 'string', length: 20, nullable: false, name: 'discount_type')]
    private string $discountType;

    #[ORM\Column(type: 'float', nullable: false, name: 'discount_value')]
    private float $discountValue;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $priority = 100;

    #[ORM\Column(type: 'boolean', nullable: false, name: 'is_active')]
    private bool $isActive = false;

    #[ORM\Column(type: 'string', length: 20, nullable: true, name: 'coupon_code')]
    private ?string $couponCode = null;

    #[ORM\Column(type: 'json', nullable: false)]
    private array $conditions = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'valid_from')]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'valid_to')]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: false, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false, name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(Promotion $promotion): self
    {
        $entity = new self();
        $entity->id = $promotion->id()->toString();
        $entity->tenantId = $promotion->tenantId()->toString();
        $entity->name = $promotion->name();
        $entity->type = $promotion->type()->toString();
        $entity->discountType = $promotion->discount()->type()->toString();
        $entity->discountValue = $promotion->discount()->value();
        $entity->priority = $promotion->priority();
        $entity->isActive = $promotion->isActive();
        $entity->couponCode = $promotion->couponCode()?->toString();
        $entity->conditions = $promotion->conditions();
        $entity->validFrom = $promotion->validFrom();
        $entity->validTo = $promotion->validTo();
        $entity->createdAt = $promotion->createdAt();
        $entity->updatedAt = $promotion->updatedAt();

        return $entity;
    }

    public static function fromDTO(\App\Pricing\Application\DTO\PromotionDTO $dto): self
    {
        $entity = new self();
        $entity->id = $dto->id;
        $entity->tenantId = $dto->tenantId;
        $entity->name = $dto->name;
        $entity->type = $dto->type;
        $entity->discountType = $dto->discountType;
        $entity->discountValue = $dto->discountValue;
        $entity->priority = $dto->priority;
        $entity->isActive = $dto->isActive;
        $entity->couponCode = $dto->couponCode;
        $entity->conditions = $dto->conditions;
        $entity->validFrom = $dto->validFrom ? new \DateTimeImmutable($dto->validFrom) : null;
        $entity->validTo = $dto->validTo ? new \DateTimeImmutable($dto->validTo) : null;
        $entity->createdAt = new \DateTimeImmutable($dto->createdAt);
        $entity->updatedAt = new \DateTimeImmutable($dto->updatedAt);

        return $entity;
    }

    public function toDomainModel(): Promotion
    {
        return Promotion::reconstituteFromPersistence(
            id: PromotionId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            name: $this->name,
            type: PromotionType::fromString($this->type),
            discount: Discount::fromTypeAndValue($this->discountType, $this->discountValue),
            priority: $this->priority,
            isActive: $this->isActive,
            couponCode: null !== $this->couponCode ? CouponCode::fromString($this->couponCode) : null,
            conditions: $this->conditions,
            validFrom: $this->validFrom,
            validTo: $this->validTo,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    public function updateFromDomainModel(Promotion $promotion): void
    {
        $this->name = $promotion->name();
        $this->discountType = $promotion->discount()->type()->toString();
        $this->discountValue = $promotion->discount()->value();
        $this->priority = $promotion->priority();
        $this->isActive = $promotion->isActive();
        $this->conditions = $promotion->conditions();
        $this->validFrom = $promotion->validFrom();
        $this->validTo = $promotion->validTo();
        $this->updatedAt = $promotion->updatedAt();
    }

    // Getters
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

    public function getType(): string
    {
        return $this->type;
    }

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function getDiscountValue(): float
    {
        return $this->discountValue;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters (for API Platform deserialization)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setDiscountType(string $discountType): void
    {
        $this->discountType = $discountType;
    }

    public function setDiscountValue(float $discountValue): void
    {
        $this->discountValue = $discountValue;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setCouponCode(?string $couponCode): void
    {
        $this->couponCode = $couponCode;
    }

    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): void
    {
        $this->validTo = $validTo;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
