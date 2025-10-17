<?php

declare(strict_types=1);

namespace App\Review\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\ValueObject\Rating;
use App\Review\Domain\ValueObject\ReviewStatus;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

/**
 * Doctrine entity for ProductReview
 * Translatable fields: title, content
 */
#[ORM\Entity]
#[ORM\Table(name: 'product_reviews')]
#[ORM\Index(name: 'idx_reviews_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_reviews_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_reviews_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_reviews_status', columns: ['status'])]
#[ORM\Index(name: 'idx_reviews_product_status', columns: ['product_id', 'status'])]
class ProductReviewEntity implements Translatable
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'product_id')]
    private string $productId;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36, name: 'customer_id', nullable: true)]
    private ?string $customerId = null;

    #[ORM\Column(type: 'string', length: 255, name: 'customer_name', nullable: true)]
    private ?string $customerName = null;

    #[ORM\Column(type: 'smallint')]
    private int $rating;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $title = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'boolean', name: 'is_verified_purchase')]
    private bool $isVerifiedPurchase = false;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[Gedmo\Locale]
    private string $locale;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Create entity from domain model
     */
    public static function fromDomainModel(ProductReview $review): self
    {
        $entity = new self();
        $entity->id = $review->id()->toString();
        $entity->productId = $review->productId()->toString();
        $entity->tenantId = $review->tenantId()->toString();
        $entity->customerId = $review->customerId();
        $entity->customerName = $review->customerName();
        $entity->rating = $review->rating()->value();
        $entity->title = $review->title();
        $entity->content = $review->content();
        $entity->status = $review->status()->value;
        $entity->isVerifiedPurchase = $review->isVerifiedPurchase();
        $entity->createdAt = $review->createdAt();
        $entity->updatedAt = $review->updatedAt();

        return $entity;
    }

    /**
     * Convert to domain model
     */
    public function toDomainModel(): ProductReview
    {
        return ProductReview::reconstituteFromPersistence(
            ReviewId::fromString($this->id),
            ProductId::fromString($this->productId),
            TenantId::fromString($this->tenantId),
            $this->customerId,
            $this->customerName,
            Rating::fromInt($this->rating),
            $this->title,
            $this->content,
            ReviewStatus::from($this->status),
            $this->isVerifiedPurchase,
            $this->createdAt,
            $this->updatedAt
        );
    }

    /**
     * Update entity from domain model
     */
    public function updateFromDomainModel(ProductReview $review): void
    {
        $this->customerName = $review->customerName();
        $this->rating = $review->rating()->value();
        $this->title = $review->title();
        $this->content = $review->content();
        $this->status = $review->status()->value;
        $this->isVerifiedPurchase = $review->isVerifiedPurchase();
        $this->updatedAt = $review->updatedAt();
    }

    public function setTranslatableLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isVerifiedPurchase(): bool
    {
        return $this->isVerifiedPurchase;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
