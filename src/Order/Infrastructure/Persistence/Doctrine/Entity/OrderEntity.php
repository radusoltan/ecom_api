<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderStatus;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_orders_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_orders_tenant_customer', columns: ['tenant_id', 'customer_email_blind_index'])]
#[ORM\Index(name: 'idx_orders_status', columns: ['status'])]
#[ORM\Index(name: 'idx_orders_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_orders_customer_created', columns: ['customer_email_blind_index', 'created_at'])]
#[ORM\Index(name: 'idx_orders_tenant_status_created', columns: ['tenant_id', 'status', 'created_at'])]
class OrderEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'encrypted_string', name: 'customer_email')]
    private string $customerEmail;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $customerEmailBlindIndex = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'json')]
    /** @var array<string, mixed> */
    private array $lines = [];

    #[ORM\Column(type: 'encrypted_json', name: 'shipping_address')]
    private array $shippingAddress;

    #[ORM\Column(type: 'encrypted_json', name: 'billing_address')]
    private array $billingAddress;

    #[ORM\Column(type: 'json', nullable: true, name: 'applied_promotions')]
    private ?array $appliedPromotions = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'discount_amount')]
    private ?int $discountAmount = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'discount_currency')]
    private ?string $discountCurrency = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true, name: 'coupon_code')]
    private ?string $couponCode = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'tax_amount')]
    private ?int $taxAmount = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'tax_currency')]
    private ?string $taxCurrency = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true, name: 'tax_jurisdiction')]
    private ?string $taxJurisdiction = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'tax_rule_id')]
    private ?string $taxRuleId = null;

    #[ORM\Column(type: 'float', nullable: true, name: 'tax_rate')]
    private ?float $taxRate = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'is_reverse_charge')]
    private bool $isReverseCharge = false;

    #[ORM\Column(type: 'encrypted_string', nullable: true, name: 'vat_number')]
    private ?string $vatNumber = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(Order $order): self
    {
        $entity = new self();
        $entity->id = $order->id()->toString();
        $entity->tenantId = $order->tenantId()->toString();
        $entity->customerEmail = $order->customerEmail();
        $entity->status = $order->status()->value();

        $entity->lines = array_map(
            fn (OrderLine $line) => [
                'productId' => $line->productId()->toString(),
                'productName' => $line->productName(),
                'quantity' => $line->quantity(),
                'unitPriceAmount' => $line->unitPrice()->getAmount(),
                'unitPriceCurrency' => $line->unitPrice()->getCurrency()->getCurrencyCode(),
            ],
            $order->lines()
        );

        $entity->shippingAddress = [
            'street' => $order->shippingAddress()->street(),
            'city' => $order->shippingAddress()->city(),
            'state' => $order->shippingAddress()->state(),
            'postalCode' => $order->shippingAddress()->postalCode(),
            'country' => $order->shippingAddress()->country(),
        ];

        $entity->billingAddress = [
            'street' => $order->billingAddress()->street(),
            'city' => $order->billingAddress()->city(),
            'state' => $order->billingAddress()->state(),
            'postalCode' => $order->billingAddress()->postalCode(),
            'country' => $order->billingAddress()->country(),
        ];

        $entity->appliedPromotions = $order->appliedPromotions();
        $entity->couponCode = $order->couponCode();

        if (null !== $order->discountAmount()) {
            $entity->discountAmount = $order->discountAmount()->getAmount();
            $entity->discountCurrency = $order->discountAmount()->getCurrency()->getCurrencyCode();
        }

        if (null !== $order->taxAmount()) {
            $entity->taxAmount = $order->taxAmount()->getAmount();
            $entity->taxCurrency = $order->taxAmount()->getCurrency()->getCurrencyCode();
        }
        $entity->taxJurisdiction = $order->taxJurisdiction();
        $entity->taxRuleId = $order->taxRuleId();
        $entity->taxRate = $order->taxRate();
        $entity->isReverseCharge = $order->isReverseCharge();
        $entity->vatNumber = $order->vatNumber();

        $entity->createdAt = $order->createdAt();
        $entity->updatedAt = $order->updatedAt();

        return $entity;
    }

    /**
     * Update this entity's properties from a domain model
     * Used for updating existing entities without creating new instances.
     */
    public function updateFromDomainModel(Order $order): void
    {
        // Note: id and tenantId should never change
        $this->customerEmail = $order->customerEmail();
        $this->status = $order->status()->value();

        $this->lines = array_map(
            fn (OrderLine $line) => [
                'productId' => $line->productId()->toString(),
                'productName' => $line->productName(),
                'quantity' => $line->quantity(),
                'unitPriceAmount' => $line->unitPrice()->getAmount(),
                'unitPriceCurrency' => $line->unitPrice()->getCurrency()->getCurrencyCode(),
            ],
            $order->lines()
        );

        $this->shippingAddress = [
            'street' => $order->shippingAddress()->street(),
            'city' => $order->shippingAddress()->city(),
            'state' => $order->shippingAddress()->state(),
            'postalCode' => $order->shippingAddress()->postalCode(),
            'country' => $order->shippingAddress()->country(),
        ];

        $this->billingAddress = [
            'street' => $order->billingAddress()->street(),
            'city' => $order->billingAddress()->city(),
            'state' => $order->billingAddress()->state(),
            'postalCode' => $order->billingAddress()->postalCode(),
            'country' => $order->billingAddress()->country(),
        ];

        $this->appliedPromotions = $order->appliedPromotions();
        $this->couponCode = $order->couponCode();

        if (null !== $order->discountAmount()) {
            $this->discountAmount = $order->discountAmount()->getAmount();
            $this->discountCurrency = $order->discountAmount()->getCurrency()->getCurrencyCode();
        } else {
            $this->discountAmount = null;
            $this->discountCurrency = null;
        }

        if (null !== $order->taxAmount()) {
            $this->taxAmount = $order->taxAmount()->getAmount();
            $this->taxCurrency = $order->taxAmount()->getCurrency()->getCurrencyCode();
        } else {
            $this->taxAmount = null;
            $this->taxCurrency = null;
        }
        $this->taxJurisdiction = $order->taxJurisdiction();
        $this->taxRuleId = $order->taxRuleId();
        $this->taxRate = $order->taxRate();
        $this->isReverseCharge = $order->isReverseCharge();
        $this->vatNumber = $order->vatNumber();

        $this->updatedAt = $order->updatedAt();
    }

    public function toDomainModel(): Order
    {
        $lines = array_map(
            fn (array $lineData) => OrderLine::create(
                ProductId::fromString($lineData['productId']),
                $lineData['productName'],
                $lineData['quantity'],
                Money::fromScalars($lineData['unitPriceAmount'], $lineData['unitPriceCurrency'])
            ),
            $this->lines
        );

        $shippingAddress = Address::create(
            $this->shippingAddress['street'],
            $this->shippingAddress['city'],
            $this->shippingAddress['state'],
            $this->shippingAddress['postalCode'],
            $this->shippingAddress['country']
        );

        $billingAddress = Address::create(
            $this->billingAddress['street'],
            $this->billingAddress['city'],
            $this->billingAddress['state'],
            $this->billingAddress['postalCode'],
            $this->billingAddress['country']
        );

        $discountAmount = null;
        if (null !== $this->discountAmount && null !== $this->discountCurrency) {
            $discountAmount = Money::fromScalars($this->discountAmount, $this->discountCurrency);
        }

        $taxAmount = null;
        if (null !== $this->taxAmount && null !== $this->taxCurrency) {
            $taxAmount = Money::fromScalars($this->taxAmount, $this->taxCurrency);
        }

        return Order::reconstituteFromPersistence(
            OrderId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            $this->customerEmail,
            OrderStatus::fromString($this->status),
            $lines,
            $shippingAddress,
            $billingAddress,
            $this->createdAt,
            $this->updatedAt,
            $this->appliedPromotions ?? [],
            $discountAmount,
            $this->couponCode,
            $taxAmount,
            $this->taxJurisdiction,
            $this->taxRuleId,
            $this->taxRate ?? 0.0,
            $this->isReverseCharge,
            $this->vatNumber,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLines(): array
    {
        return $this->lines;
    }

    public function getShippingAddress(): array
    {
        return $this->shippingAddress;
    }

    public function getBillingAddress(): array
    {
        return $this->billingAddress;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAppliedPromotions(): ?array
    {
        return $this->appliedPromotions;
    }

    public function getDiscountAmount(): ?float
    {
        return $this->discountAmount;
    }

    public function getDiscountCurrency(): ?string
    {
        return $this->discountCurrency;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function getTaxAmount(): ?float
    {
        return $this->taxAmount;
    }

    public function getTaxCurrency(): ?string
    {
        return $this->taxCurrency;
    }

    public function getTaxJurisdiction(): ?string
    {
        return $this->taxJurisdiction;
    }

    public function getTaxRuleId(): ?string
    {
        return $this->taxRuleId;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate;
    }

    public function isReverseCharge(): bool
    {
        return $this->isReverseCharge;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function getCustomerEmailBlindIndex(): ?string
    {
        return $this->customerEmailBlindIndex;
    }

    public function setCustomerEmailBlindIndex(?string $customerEmailBlindIndex): void
    {
        $this->customerEmailBlindIndex = $customerEmailBlindIndex;
    }
}
