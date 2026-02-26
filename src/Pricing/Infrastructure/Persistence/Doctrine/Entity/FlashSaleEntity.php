<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Model\FlashSaleStatus;
use App\Pricing\Presentation\Api\Processor\CancelFlashSaleProcessor;
use App\Pricing\Presentation\Api\Processor\CreateFlashSaleProcessor;
use App\Pricing\Presentation\Api\Provider\FlashSaleCollectionProvider;
use App\Pricing\Presentation\Api\Provider\FlashSaleItemProvider;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'flash_sales')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_flash_sales_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_flash_sales_status', columns: ['status'])]
#[ORM\Index(name: 'idx_flash_sales_start_time', columns: ['start_time'])]
#[ORM\Index(name: 'idx_flash_sales_end_time', columns: ['end_time'])]
#[ORM\Index(name: 'idx_flash_sales_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_flash_sales_created_at', columns: ['created_at'])]
#[ApiResource(
    shortName: 'FlashSale',
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
    operations: [
        new Get(provider: FlashSaleItemProvider::class),
        new GetCollection(provider: FlashSaleCollectionProvider::class),
        new Post(processor: CreateFlashSaleProcessor::class),
        new Patch(
            uriTemplate: '/flash-sales/{id}/cancel',
            processor: CancelFlashSaleProcessor::class
        ),
        new Delete(),
    ]
)]
class FlashSaleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private string $name;

    #[ORM\Column(type: 'json', nullable: false, name: 'product_ids')]
    private array $productIds = [];

    #[ORM\Column(type: 'string', length: 20, nullable: false, name: 'discount_type')]
    private string $discountType;

    #[ORM\Column(type: 'float', nullable: false, name: 'discount_value')]
    private float $discountValue;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false, name: 'start_time')]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false, name: 'end_time')]
    private \DateTimeImmutable $endTime;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $status;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false, name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(FlashSale $flashSale): self
    {
        $entity = new self();
        $entity->id = $flashSale->id()->toString();
        $entity->tenantId = $flashSale->tenantId()->toString();
        $entity->name = $flashSale->name();
        $entity->productIds = array_map(
            fn (ProductId $productId) => $productId->toString(),
            $flashSale->productIds()
        );
        $entity->discountType = $flashSale->discount()->type()->toString();
        $entity->discountValue = $flashSale->discount()->value();
        $entity->startTime = $flashSale->startTime();
        $entity->endTime = $flashSale->endTime();
        $entity->status = $flashSale->status()->value;
        $entity->createdAt = $flashSale->createdAt();
        $entity->updatedAt = $flashSale->updatedAt();

        return $entity;
    }

    public function toDomainModel(): FlashSale
    {
        return FlashSale::reconstituteFromPersistence(
            id: FlashSaleId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            name: $this->name,
            productIds: array_map(
                fn (string $productId) => ProductId::fromString($productId),
                $this->productIds
            ),
            discount: Discount::fromTypeAndValue($this->discountType, $this->discountValue),
            startTime: $this->startTime,
            endTime: $this->endTime,
            status: FlashSaleStatus::from($this->status),
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    public function updateFromDomainModel(FlashSale $flashSale): void
    {
        $this->status = $flashSale->status()->value;
        $this->updatedAt = $flashSale->updatedAt();
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

    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function getDiscountValue(): float
    {
        return $this->discountValue;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getStatus(): string
    {
        return $this->status;
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

    public function setProductIds(array $productIds): void
    {
        $this->productIds = $productIds;
    }

    public function setDiscountType(string $discountType): void
    {
        $this->discountType = $discountType;
    }

    public function setDiscountValue(float $discountValue): void
    {
        $this->discountValue = $discountValue;
    }

    public function setStartTime(\DateTimeImmutable $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
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
