<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Pricing\Domain\ValueObject\PriceChangeSource;
use App\Pricing\Infrastructure\ApiPlatform\State\PriceHistoryProvider;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use Brick\Money\Currency;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Price History Entity - Infrastructure adapter for price change audit log.
 *
 * This entity persists price changes to the database for audit trail,
 * compliance, and analytics purposes.
 *
 * Business Rules:
 * - Keep history for at least 2 years (configurable)
 * - Immutable records (no updates/deletes)
 * - Required for EU consumer protection laws
 * - Must show "lowest price in last 30 days" before discounts
 */
#[ORM\Entity]
#[ORM\Table(name: 'price_history')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_price_history_tenant')]
#[ORM\Index(columns: ['product_id'], name: 'idx_price_history_product')]
#[ORM\Index(columns: ['changed_at'], name: 'idx_price_history_changed_at')]
#[ORM\Index(columns: ['tenant_id', 'product_id', 'changed_at'], name: 'idx_price_history_tenant_product_date')]
#[ORM\Index(columns: ['source'], name: 'idx_price_history_source')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/v1/price-history/{id}',
            provider: PriceHistoryProvider::class
        ),
        new GetCollection(
            uriTemplate: '/v1/price-history',
            provider: PriceHistoryProvider::class
        ),
        new GetCollection(
            uriTemplate: '/v1/products/{productId}/price-history',
            uriVariables: ['productId'],
            provider: PriceHistoryProvider::class
        ),
    ],
    normalizationContext: ['groups' => ['price_history:read']],
    paginationItemsPerPage: 50,
    paginationMaximumItemsPerPage: 100
)]
class PriceHistoryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    #[ApiProperty(identifier: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36)]
    private string $productId;

    #[ORM\Column(type: Types::DECIMAL, precision: 19, scale: 4, nullable: true)]
    private ?string $oldPriceAmount = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $oldPriceCurrency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 19, scale: 4, nullable: true)]
    private ?string $newPriceAmount = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $newPriceCurrency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changeReason = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $changedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    #[ORM\Column(type: 'string', length: 20)]
    private string $source;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
        $this->changedAt = new \DateTimeImmutable();
    }

    /**
     * Creates entity from PriceChange value object.
     */
    public static function fromPriceChange(
        PriceChange $priceChange,
        TenantId $tenantId,
        ?UserId $changedBy = null
    ): self {
        $entity = new self();
        $entity->tenantId = $tenantId->toString();
        $entity->productId = $priceChange->productId()->toString();

        if ($priceChange->oldPrice() !== null) {
            $entity->oldPriceAmount = (string) $priceChange->oldPrice()->amount();
            $entity->oldPriceCurrency = $priceChange->oldPrice()->currency()->getCurrencyCode();
        }

        if ($priceChange->newPrice() !== null) {
            $entity->newPriceAmount = (string) $priceChange->newPrice()->amount();
            $entity->newPriceCurrency = $priceChange->newPrice()->currency()->getCurrencyCode();
        }

        $entity->changeReason = $priceChange->reason();
        $entity->changedBy = $changedBy?->toString();
        $entity->changedAt = $priceChange->timestamp();
        $entity->source = $priceChange->source()->value;

        return $entity;
    }

    /**
     * Converts entity to PriceChange value object.
     */
    public function toPriceChange(): PriceChange
    {
        $oldPrice = null;
        if ($this->oldPriceAmount !== null && $this->oldPriceCurrency !== null) {
            $oldPrice = Money::of(
                $this->oldPriceAmount,
                $this->oldPriceCurrency
            );
        }

        $newPrice = null;
        if ($this->newPriceAmount !== null && $this->newPriceCurrency !== null) {
            $newPrice = Money::of(
                $this->newPriceAmount,
                $this->newPriceCurrency
            );
        }

        return PriceChange::create(
            ProductId::fromString($this->productId),
            $oldPrice,
            $newPrice,
            $this->changeReason,
            PriceChangeSource::fromString($this->source),
            $this->changedAt
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function oldPriceAmount(): ?string
    {
        return $this->oldPriceAmount;
    }

    public function oldPriceCurrency(): ?string
    {
        return $this->oldPriceCurrency;
    }

    public function newPriceAmount(): ?string
    {
        return $this->newPriceAmount;
    }

    public function newPriceCurrency(): ?string
    {
        return $this->newPriceCurrency;
    }

    public function changeReason(): ?string
    {
        return $this->changeReason;
    }

    public function changedBy(): ?string
    {
        return $this->changedBy;
    }

    public function changedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * Calculates the lowest price in the last N days.
     * Required for EU consumer protection laws.
     */
    public function getOldPrice(): ?array
    {
        if ($this->oldPriceAmount === null || $this->oldPriceCurrency === null) {
            return null;
        }

        return [
            'amount' => (float) $this->oldPriceAmount,
            'currency' => $this->oldPriceCurrency,
        ];
    }

    public function getNewPrice(): ?array
    {
        if ($this->newPriceAmount === null || $this->newPriceCurrency === null) {
            return null;
        }

        return [
            'amount' => (float) $this->newPriceAmount,
            'currency' => $this->newPriceCurrency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'product_id' => $this->productId,
            'old_price' => $this->getOldPrice(),
            'new_price' => $this->getNewPrice(),
            'change_reason' => $this->changeReason,
            'changed_by' => $this->changedBy,
            'changed_at' => $this->changedAt->format(\DateTimeImmutable::ATOM),
            'source' => $this->source,
        ];
    }
}
